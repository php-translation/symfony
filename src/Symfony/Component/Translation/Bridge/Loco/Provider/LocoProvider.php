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
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\LoaderInterface;
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
final class LocoProvider
{
    private $client;
    private $loader;
    private $logger;
    private $defaultLocale;

    public function __construct(HttpClientInterface $client, LoaderInterface $loader, LoggerInterface $logger, string $defaultLocale)
    {
        $this->client = $client;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
    }

    public function getName(): string
    {
        return LocoProviderFactory::SCHEME;
    }

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
            foreach ($catalogue->all() as $messages) {
                foreach ($messages as $id => $message) {
                    $this->translateAsset($id, $message, $locale);
                }
            }
        }
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $filter = $domains ? implode(',', $domains) : '*';
        $translatorBag = new TranslatorBag();

        foreach ($locales as $locale) {
            $response = $this->client->request('GET', sprintf('/export/locale/%s.xlf?filter=%s', $locale, $filter));
            $responseContent = $response->getContent(false);

            if (200 !== $response->getStatusCode()) {
                throw new ProviderException('Unable to read the Loco response: '.$responseContent, $response);
            }

            foreach ($domains as $domain) {
                $translatorBag->addCatalogue($this->loader->load($responseContent, $locale, $domain));
            }
        }

        return $translatorBag;
    }

    public function delete(TranslatorBag $translatorBag): void
    {
        $deletedIds = [];

        foreach ($translatorBag->all() as $domainMessages) {
            foreach ($domainMessages as $messages) {
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

    private function createAsset(string $id): void
    {
        $response = $this->client->request('POST', '/assets', [
            'body' => [
                'name' => $id,
                'id' => $id,
                'type' => 'text',
                'default' => 'untranslated',
            ],
        ]);

        if (409 === $response->getStatusCode()) {
            $this->logger->info(sprintf('Translation key (%s) already exists in Loco.', $id), [
                'id' => $id,
            ]);
        } elseif (201 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add new translation key (%s) to Loco: (status code: "%s") "%s".', $id, $response->getStatusCode(), $response->getContent(false)), $response);
        }
    }

    private function translateAsset(string $id, string $message, string $locale): void
    {
        $response = $this->client->request('POST', sprintf('/translations/%s/%s', $id, $locale), [
            'body' => $message,
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add translation message "%s" (for key: "%s" in locale "%s") to Loco: "%s".', $message, $id, $locale, $response->getContent(false)), $response);
        }
    }

    private function tagsAssets(array $ids, string $tag): void
    {
        $idsAsString = implode(',', array_unique($ids));

        if (!\in_array($tag, $this->getTags())) {
            $this->createTag($tag);
        }

        $response = $this->client->request('POST', sprintf('/tags/%s.json', $tag), [
            'body' => $idsAsString,
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add tag (%s) on translation keys (%s) to Loco: "%s".', $tag, $idsAsString, $response->getContent(false)), $response);
        }
    }

    private function createTag(string $tag): void
    {
        $response = $this->client->request('POST', '/tags.json', [
            'body' => [
                'name' => $tag,
            ],
        ]);

        if (201 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to create tag (%s) on Loco: "%s".', $tag, $response->getContent(false)), $response);
        }
    }

    private function getTags(): array
    {
        $response = $this->client->request('GET', '/tags.json');
        $content = $response->getContent(false);

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to get tags on Loco: "%s".', $content), $response);
        }

        return json_decode($content) ?: [];
    }

    private function deleteAsset(string $id): void
    {
        $response = $this->client->request('DELETE', sprintf('/assets/%s.json', $id));

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add new translation key (%s) to Loco: "%s".', $id, $response->getContent(false)), $response);
        }
    }
}
