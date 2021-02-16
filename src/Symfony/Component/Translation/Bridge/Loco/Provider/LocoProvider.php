<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Loco\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\AbstractProvider;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * @experimental in 5.3
 *
 * In Loco:
 * Tags refers to Symfony's translation domains;
 * Assets refers to Symfony's translation keys;
 * Translations refers to Symfony's translated messages
 */
final class LocoProvider extends AbstractProvider
{
    protected const HOST = 'localise.biz/api';

    /** @var string */
    private $apiKey;

    /** @var LoaderInterface|null */
    private $loader;

    /** @var LoggerInterface|null */
    private $logger;

    /** @var string|null */
    private $defaultLocale;

    public function __construct(string $apiKey, HttpClientInterface $client = null, LoaderInterface $loader = null, LoggerInterface $logger = null, string $defaultLocale = null)
    {
        $this->apiKey = $apiKey;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;

        parent::__construct($client);
    }

    public function __toString(): string
    {
        return sprintf(LocoProviderFactory::SCHEME.'://%s', $this->getEndpoint());
    }

    public function getName(): string
    {
        return LocoProviderFactory::SCHEME;
    }

    /**
     * {@inheritdoc}
     */
    public function write(TranslatorBag $translatorBag): void
    {
        $catalogue = $translatorBag->getCatalogue($this->defaultLocale);

        if (!$catalogue) {
            $catalogue = $translatorBag->getCatalogues()[0];
        }

        // Create keys on Loco
        foreach ($catalogue->all() as $domain => $messages) {
            $ids = [];
            foreach ($messages as $id => $message) {
                $ids[] = $id;
                $this->createAsset($id);
            }
            if ($ids) {
                $this->tagsAssets($ids, $domain);
            }
        }

        // Push translations in all locales and tag them with domain
        foreach ($translatorBag->getCatalogues() as $catalogue) {
            $locale = $catalogue->getLocale();
            foreach ($catalogue->all() as $domain => $messages) {
                foreach ($messages as $id => $message) {
                    $this->translateAsset($id, $message, $locale);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function read(array $domains, array $locales): TranslatorBag
    {
        $filter = $domains ? implode(',', $domains) : '*';
        $translatorBag = new TranslatorBag();

        foreach ($locales as $locale) {
            $response = $this->client->request('GET', sprintf('https://%s/export/locale/%s.xlf?filter=%s', $this->getEndpoint(), $locale, $filter), [
                'headers' => $this->getDefaultHeaders(),
            ]);

            $responseContent = $response->getContent(false);

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                throw new ProviderException('Unable to read the Loco response: '.$responseContent, $response);
            }

            foreach ($domains as $domain) {
                $translatorBag->addCatalogue($this->loader->load($responseContent, $locale, $domain));
            }
        }

        return $translatorBag;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(TranslatorBag $translatorBag): void
    {
        $deletedIds = [];

        foreach ($translatorBag->all() as $locale => $domainMessages) {
            foreach ($domainMessages as $domain => $messages) {
                foreach ($messages as $id => $message) {
                    if (\in_array($id, $deletedIds)) {
                        continue;
                    }

                    $this->deleteAsset($id);

                    $deletedIds[] = $id;
                }
            }
        }
    }

    protected function getDefaultHeaders(): array
    {
        return [
            'Authorization' => 'Loco '.$this->apiKey,
        ];
    }

    /**
     * This function allows creation of a new translation key.
     */
    private function createAsset(string $id): void
    {
        $response = $this->client->request('POST', sprintf('https://%s/assets', $this->getEndpoint()), [
            'headers' => $this->getDefaultHeaders(),
            'body' => [
                'name' => $id,
                'id' => $id,
                'type' => 'text',
                'default' => 'untranslated',
            ],
        ]);

        if (Response::HTTP_CONFLICT === $response->getStatusCode()) {
            $this->logger->info(sprintf('Translation key (%s) already exists in Loco.', $id), [
                'id' => $id,
            ]);
        } elseif (Response::HTTP_CREATED !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add new translation key (%s) to Loco: (status code: "%s") "%s".', $id, $response->getStatusCode(), $response->getContent(false)), $response);
        }
    }

    private function translateAsset(string $id, string $message, string $locale): void
    {
        $response = $this->client->request('POST', sprintf('https://%s/translations/%s/%s', $this->getEndpoint(), $id, $locale), [
            'headers' => $this->getDefaultHeaders(),
            'body' => $message,
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add translation message "%s" (for key: "%s" in locale "%s") to Loco: "%s".', $message, $id, $locale, $response->getContent(false)), $response);
        }
    }

    private function tagsAssets(array $ids, string $tag): void
    {
        $idsAsString = implode(',', array_unique($ids));

        if (!\in_array($tag, $this->getTags())) {
            $this->createTag($tag);
        }

        $response = $this->client->request('POST', sprintf('https://%s/tags/%s.json', $this->getEndpoint(), $tag), [
            'headers' => $this->getDefaultHeaders(),
            'body' => $idsAsString,
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add tag (%s) on translation keys (%s) to Loco: "%s".', $tag, $idsAsString, $response->getContent(false)), $response);
        }
    }

    private function createTag(string $tag): void
    {
        $response = $this->client->request('POST', sprintf('https://%s/tags.json', $this->getEndpoint()), [
            'headers' => $this->getDefaultHeaders(),
            'body' => [
                'name' => $tag,
            ],
        ]);

        if (Response::HTTP_CREATED !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to create tag (%s) on Loco: "%s".', $tag, $response->getContent(false)), $response);
        }
    }

    private function getTags(): array
    {
        $response = $this->client->request('GET', sprintf('https://%s/tags.json', $this->getEndpoint()), [
            'headers' => $this->getDefaultHeaders(),
        ]);

        $content = $response->getContent(false);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to get tags on Loco: "%s".', $content), $response);
        }

        return json_decode($content) ?: [];
    }

    private function deleteAsset(string $id): void
    {
        $response = $this->client->request('DELETE', sprintf('https://%s/assets/%s.json', $this->getEndpoint(), $id), [
            'headers' => $this->getDefaultHeaders(),
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add new translation key (%s) to Loco: "%s".', $id, $response->getContent(false)), $response);
        }
    }
}
