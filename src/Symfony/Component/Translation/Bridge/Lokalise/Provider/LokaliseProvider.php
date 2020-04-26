<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Lokalise\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\AbstractProvider;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * @experimental in 5.3
 *
 * In Lokalise:
 * Filenames refers to Symfony's translation domains;
 * Keys refers to Symfony's translation keys;
 * Translations refers to Symfony's translated messages
 */
final class LokaliseProvider extends AbstractProvider
{
    protected const HOST = 'api.lokalise.com/api2';

    /** @var string */
    private $apiKey;

    /** @var string */
    private $projectId;

    /** @var LoaderInterface|null */
    private $loader;

    /** @var LoggerInterface|null */
    private $logger;

    /** @var string|null */
    private $defaultLocale;

    public function __construct(string $projectId, string $apiKey, HttpClientInterface $client = null, LoaderInterface $loader = null, LoggerInterface $logger = null, string $defaultLocale = null)
    {
        $this->projectId = $projectId;
        $this->apiKey = $apiKey;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;

        parent::__construct($client);
    }

    public function __toString(): string
    {
        return sprintf(LokaliseProviderFactory::SCHEME.'://%s', $this->getEndpoint());
    }

    public function getName(): string
    {
        return LokaliseProviderFactory::SCHEME;
    }

    /**
     * {@inheritdoc}
     */
    public function write(TranslatorBag $translatorBag): void
    {
        $this->createKeysWithTranslations($translatorBag);
    }

    /**
     * {@inheritdoc}
     */
    public function read(array $domains, array $locales): TranslatorBag
    {
        $translatorBag = new TranslatorBag();
        $translations = $this->exportFiles($locales, $domains);

        foreach ($translations as $locale => $files) {
            foreach ($files as $filename => $content) {
                $intlDomain = $domain = str_replace('.xliff', '', $filename);
                $suffixLength = \strlen(MessageCatalogue::INTL_DOMAIN_SUFFIX);
                if (\strlen($domain) > $suffixLength && false !== strpos($domain, MessageCatalogue::INTL_DOMAIN_SUFFIX, -$suffixLength)) {
                    $intlDomain .= MessageCatalogue::INTL_DOMAIN_SUFFIX;
                }

                if (\in_array($intlDomain, $domains)) {
                    $translatorBag->addCatalogue($this->loader->load($content['content'], $locale, $intlDomain));
                } else {
                    $this->logger->info(sprintf('The translations fetched from Lokalise under the filename "%s" does not match with any domains of your application.', $filename));
                }
            }
        }

        return $translatorBag;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(TranslatorBag $translatorBag): void
    {
        $catalogue = $translatorBag->getCatalogue($this->defaultLocale);

        if (!$catalogue) {
            $catalogue = $translatorBag->getCatalogues()[0];
        }

        $keysIds = [];
        foreach ($catalogue->all() as $messagesByDomains) {
            foreach ($messagesByDomains as $domain => $messages) {
                $keysToDelete = [];
                foreach ($messages as $message) {
                    $keysToDelete[] = $message;
                }
                $keysIds += $this->getKeysIds($keysToDelete, $domain);
            }
        }

        $response = $this->client->request('DELETE', sprintf('https://%s/projects/%s/keys', $this->getEndpoint(), $this->projectId), [
            'headers' => $this->getDefaultHeaders(),
            'body' => json_encode(['keys' => $keysIds]),
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to delete keys from Lokalise: "%s".', $response->getContent(false)), $response);
        }
    }

    protected function getDefaultHeaders(): array
    {
        return [
            'X-Api-Token' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Lokalise API recommends sending payload in chunks of up to 500 keys per request.
     *
     * @see https://app.lokalise.com/api2docs/curl/#transition-create-keys-post
     */
    private function createKeysWithTranslations(TranslatorBag $translatorBag): void
    {
        $keys = [];
        $catalogue = $translatorBag->getCatalogue($this->defaultLocale);

        if (!$catalogue) {
            $catalogue = $translatorBag->getCatalogues()[0];
        }

        foreach ($translatorBag->getDomains() as $domain) {
            foreach ($catalogue->all($domain) as $key => $message) {
                $keys[] = [
                    'key_name' => $key,
                    'platforms' => ['web'],
                    'filenames' => [
                        'web' => $this->generateLokaliseFilenameFromDomain($domain),
                        // There is a bug in Lokalise with "Per platform key names" option enabled,
                        // we need to provide a filename for all platforms.
                        'ios' => null,
                        'android' => null,
                        'other' => null,
                    ],
                    'translations' => array_map(function ($catalogue) use ($key, $domain) {
                        return [
                            'language_iso' => $catalogue->getLocale(),
                            'translation' => $catalogue->get($key, $domain),
                        ];
                    }, $translatorBag->getCatalogues()),
                ];
            }
        }

        $chunks = array_chunk($keys, 500);

        foreach ($chunks as $chunk) {
            $response = $this->client->request('POST', sprintf('https://%s/projects/%s/keys', $this->getEndpoint(), $this->projectId), [
                'headers' => $this->getDefaultHeaders(),
                'body' => json_encode(['keys' => $chunk]),
            ]);

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                throw new ProviderException(sprintf('Unable to add keys and translations to Lokalise: "%s".', $response->getContent(false)), $response);
            }
        }
    }

    /**
     * @see https://app.lokalise.com/api2docs/curl/#transition-download-files-post
     */
    private function exportFiles(array $locales, array $domains): array
    {
        $response = $this->client->request('POST', sprintf('https://%s/projects/%s/files/export', $this->getEndpoint(), $this->projectId), [
            'headers' => $this->getDefaultHeaders(),
            'body' => json_encode([
                'format' => 'symfony_xliff',
                'original_filenames' => true,
                'directory_prefix' => '%LANG_ISO%',
                'filter_langs' => array_values($locales),
                'filter_filenames' => array_map([$this, 'generateLokaliseFilenameFromDomain'], $domains),
            ]),
        ]);

        $responseContent = $response->getContent(false);

        if (Response::HTTP_NOT_ACCEPTABLE === $response->getStatusCode()
            && 'No keys found with specified filenames.' === json_decode($responseContent, true)['error']['message']) {
            return [];
        }

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to export translations from Lokalise: "%s".', $responseContent), $response);
        }

        return json_decode($responseContent, true)['files'];
    }

    private function getKeysIds(array $keys, string $domain): array
    {
        $response = $this->client->request('GET', sprintf('https://%s/projects/%s/keys', $this->getEndpoint(), $this->projectId), [
            'headers' => $this->getDefaultHeaders(),
            'query' => [
                'filter_keys' => $keys,
                'filter_filenames' => $this->generateLokaliseFilenameFromDomain($domain),
            ],
        ]);

        $responseContent = $response->getContent(false);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to get keys ids from Lokalise: "%s".', $responseContent), $response);
        }

        return array_reduce(json_decode($responseContent, true)['keys'], function ($keysIds, array $keyItem) {
            $keysIds[] = $keyItem['key_id'];

            return $keysIds;
        }, []);
    }

    private function generateLokaliseFilenameFromDomain(string $domain): string
    {
        return sprintf('%s.xliff', $domain);
    }
}
