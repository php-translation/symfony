<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\PoEditor\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * @experimental in 5.3
 *
 * In PoEditor:
 * * Terms refers to Symfony's translation keys;
 * * Translations refers to Symfony's translated messages;
 * * Tags refers to Symfony's translation domains
 */
final class PoEditorProvider implements ProviderInterface
{
    private $apiKey;
    private $projectId;
    private $client;
    private $loader;
    private $logger;
    private $defaultLocale;

    public function __construct(string $apiKey, string $projectId, HttpClientInterface $client, LoaderInterface $loader, LoggerInterface $logger, string $defaultLocale)
    {
        $this->apiKey = $apiKey;
        $this->projectId = $projectId;
        $this->client = $client;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
    }

    public function getName(): string
    {
        return PoEditorProviderFactory::SCHEME;
    }

    public function write(TranslatorBagInterface $translatorBag): void
    {
        $defaultCatalogue = $translatorBag->getCatalogue($this->defaultLocale);

        if (!$defaultCatalogue) {
            $defaultCatalogue = $translatorBag->getCatalogues()[0];
        }

        $terms = $translations = [];
        foreach ($defaultCatalogue->all() as $domain => $messages) {
            foreach ($messages as $id => $message) {
                $terms[] = [
                    'term' => $id,
                    'reference' => $id,
                    'tags' => [$domain],
                ];
            }
        }
        $this->addTerms($terms);

        foreach ($translatorBag->getCatalogues() as $catalogue) {
            $locale = $catalogue->getLocale();
            foreach ($catalogue->all() as $messages) {
                foreach ($messages as $id => $message) {
                    $translations[$locale][] = [
                        'term' => $id,
                        'translation' => [
                            'content' => $message,
                        ],
                    ];
                }
            }
        }

        $this->addTranslations($translations[$defaultCatalogue->getLocale()], $defaultCatalogue->getLocale());
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $translatorBag = new TranslatorBag();

        foreach ($locales as $locale) {
            $exportResponse = $this->client->request('POST', '/projects/export', [
                'body' => [
                    'api_token' => $this->apiKey,
                    'id' => $this->projectId,
                    'language' => $locale,
                    'type' => 'xlf',
                    'filters' => json_encode(['translated']),
                    'tags' => json_encode($domains),
                ],
             ]);

            $exportResponseContent = $exportResponse->toArray(false);

            if (200 !== $exportResponse->getStatusCode()) {
                throw new ProviderException('Unable to read the PoEditor response: '.$exportResponse->getContent(false), $exportResponse);
            }

            $response = $this->client->request('GET', $exportResponseContent['result']['url']);

            foreach ($domains as $domain) {
                $translatorBag->addCatalogue($this->loader->load($response->getContent(), $locale, $domain));
            }
        }

        return $translatorBag;
    }

    public function delete(TranslatorBagInterface $translatorBag): void
    {
        $deletedIds = $termsToDelete = [];

        foreach ($translatorBag->all() as $domainMessages) {
            foreach ($domainMessages as $messages) {
                foreach ($messages as $id => $message) {
                    if (\in_array($id, $deletedIds, true)) {
                        continue;
                    }

                    $deletedIds = $id;
                    $termsToDelete = [
                        'term' => $id,
                    ];
                }
            }
        }

        $this->deleteTerms($termsToDelete);
    }

    private function addTerms(array $terms): void
    {
        $response = $this->client->request('POST', '/terms/add', [
            'body' => [
                'api_token' => $this->apiKey,
                'id' => $this->projectId,
                'data' => json_encode($terms),
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add new translation keys to PoEditor: (status code: "%s") "%s".', $response->getStatusCode(), $response->getContent(false)), $response);
        }
    }

    private function addTranslations(array $translations, string $locale): void
    {
        $response = $this->client->request('POST', '/translations/add', [
            'body' => [
                'api_token' => $this->apiKey,
                'id' => $this->projectId,
                'language' => $locale,
                'data' => json_encode($translations),
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add translation messages to PoEditor: "%s".', $response->getContent(false)), $response);
        }
    }

    private function deleteTerms(array $ids): void
    {
        $response = $this->client->request('POST', '/terms/delete', [
            'body' => [
                'api_token' => $this->apiKey,
                'id' => $this->projectId,
                'data' => json_encode($ids),
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to delete translation keys on PoEditor: "%s".', $response->getContent(false)), $response);
        }
    }
}
