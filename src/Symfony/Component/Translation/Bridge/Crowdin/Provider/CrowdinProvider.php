<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Crowdin\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Andrii Bodnar <andrii.bodnar@crowdin.com>
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * @experimental in 5.3
 *
 * In Crowdin:
 * Filenames refers to Symfony's translation domains;
 * Identifiers refers to Symfony's translation keys;
 * Translations refers to Symfony's translated messages
 */
final class CrowdinProvider implements ProviderInterface
{
    private $projectId;
    private $client;
    private $loader;
    private $logger;
    private $defaultLocale;
    private $xliffFileDumper;
    private $files = [];

    public function __construct(string $projectId, HttpClientInterface $client, LoaderInterface $loader, LoggerInterface $logger, string $defaultLocale, XliffFileDumper $xliffFileDumper)
    {
        $this->projectId = $projectId;
        $this->client = $client;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        $this->xliffFileDumper = $xliffFileDumper;
    }

    public function getName(): string
    {
        return CrowdinProviderFactory::SCHEME;
    }

    public function write(TranslatorBagInterface $translatorBag): void
    {
        foreach ($translatorBag->getDomains() as $domain) {
            /** @var MessageCatalogue $catalogue */
            foreach ($translatorBag->getCatalogues() as $catalogue) {
                $content = $this->xliffFileDumper->formatCatalogue($catalogue, $domain);

                $fileId = $this->getFileId($domain);

                if ($catalogue->getLocale() === $this->defaultLocale) {
                    if (!$fileId) {
                        $this->addFile($domain, $content);
                    } else {
                        $this->updateFile($fileId, $domain, $content);
                    }
                } else {
                    $this->uploadTranslations($fileId, $domain, $content, $catalogue->getLocale());
                }
            }
        }
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $translatorBag = new TranslatorBag();

        foreach ($domains as $domain) {
            $fileId = $this->getFileId($domain);

            if (!$fileId) {
                continue;
            }

            foreach ($locales as $locale) {
                if ($locale !== $this->defaultLocale) {
                    $content = $this->exportProjectTranslations($locale, $fileId);
                } else {
                    $content = $this->downloadSourceFile($fileId);
                }

                $translatorBag->addCatalogue($this->loader->load($content, $locale, $domain));
            }
        }

        return $translatorBag;
    }

    public function delete(TranslatorBag $translatorBag): void
    {
        foreach ($translatorBag->all() as $locale => $domainMessages) {
            if ($locale !== $this->defaultLocale) {
                continue;
            }

            foreach ($domainMessages as $domain => $messages) {
                $fileId = $this->getFileId($domain);

                if (!$fileId) {
                    continue;
                }

                $stringsMap = $this->mapStrings($fileId);

                foreach ($messages as $id => $message) {
                    if (!isset($stringsMap[$id])) {
                        continue;
                    }

                    $this->deleteString($stringsMap[$id]);
                }
            }
        }
    }

    private function getFileId(string $domain): int
    {
        if (isset($this->files[$domain]) && $this->files[$domain]) {
            return $this->files[$domain];
        }

        $files = $this->getFilesList();

        foreach ($files as $file) {
            if ($file['data']['name'] === sprintf('%s.%s', $domain, 'xlf')) {
                $this->files[$domain] = (int) $file['data']['id'];

                return $this->files[$domain];
            }
        }

        return 0;
    }

    private function mapStrings(int $fileId): array
    {
        $result = [];

        $limit = 500;
        $offset = 0;

        do {
            $strings = $this->listStrings($fileId, $limit, $offset);

            foreach ($strings as $string) {
                $result[$string['data']['text']] = $string['data']['id'];
            }

            $offset += $limit;
        } while (\count($strings) > 0);

        return $result;
    }

    private function addFile(string $domain, string $content): void
    {
        $storageId = $this->addStorage($domain, $content);

        /**
         * @see https://support.crowdin.com/api/v2/#operation/api.projects.files.getMany (Crowdin API)
         * @see https://support.crowdin.com/enterprise/api/#operation/api.projects.files.getMany (Crowdin Enterprise API)
         */
        $response = $this->client->request('POST', sprintf('/projects/%s/files', $this->projectId), [
            'json' => [
                'storageId' => $storageId,
                'name' => sprintf('%s.%s', $domain, 'xlf'),
            ],
        ]);

        $responseContent = $response->getContent(false);

        if (201 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add a File in Crowdin for domain "%s": "%s".', $domain, $responseContent), $response);
        }
    }

    private function updateFile(int $fileId, string $domain, string $content): void
    {
        $storageId = $this->addStorage($domain, $content);

        /**
         * @see https://support.crowdin.com/api/v2/#operation/api.projects.files.put (Crowdin API)
         * @see https://support.crowdin.com/enterprise/api/#operation/api.projects.files.put (Crowdin Enterprise API)
         */
        $response = $this->client->request('PUT', sprintf('/projects/%s/files/%d', $this->projectId, $fileId), [
            'json' => [
                'storageId' => $storageId,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to update file in Crowdin for file ID "%d" and domain "%s".', $fileId, $domain), $response);
        }
    }

    private function uploadTranslations(int $fileId, string $domain, string $content, string $locale): void
    {
        if (!$fileId) {
            return;
        }

        $storageId = $this->addStorage($domain, $content);

        /**
         * @see https://support.crowdin.com/api/v2/#operation/api.projects.translations.postOnLanguage (Crowdin API)
         * @see https://support.crowdin.com/enterprise/api/#operation/api.projects.translations.postOnLanguage (Crowdin Enterprise API)
         */
        $response = $this->client->request('POST', sprintf('/projects/%s/translations/%s', $this->projectId, $locale), [
            'json' => [
                'storageId' => $storageId,
                'fileId' => $fileId,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to upload translations to Crowdin for domain "%s" and locale "%s".', $domain, $locale), $response);
        }
    }

    private function exportProjectTranslations(string $languageId, int $fileId): string
    {
        /**
         * @see https://support.crowdin.com/api/v2/#operation/api.projects.translations.exports.post (Crowdin API)
         * @see https://support.crowdin.com/enterprise/api/#operation/api.projects.translations.exports.post (Crowdin Enterprise API)
         */
        $response = $this->client->request('POST', sprintf('/projects/%d/translations/exports', $this->projectId), [
            'json' => [
                'targetLanguageId' => $languageId,
                'fileIds' => [$fileId],
            ],
        ]);

        if (204 === $response->getStatusCode()) {
            throw new ProviderException(sprintf('No content in exported file %d.', $fileId), $response);
        }

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to export file %d translations for language "%s".', $fileId, $languageId), $response);
        }

        $export = json_decode($response->getContent(), true);

        $exportResponse = $this->client->request('GET', $export['data']['url']);

        if (200 !== $exportResponse->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to download file %d translations content for language "%s".', $fileId, $languageId), $exportResponse);
        }

        return $exportResponse->getContent();
    }

    private function downloadSourceFile(int $fileId): string
    {
        /**
         * @see https://support.crowdin.com/api/v2/#operation/api.projects.files.download.get (Crowdin API)
         * @see https://support.crowdin.com/enterprise/api/#operation/api.projects.files.download.get (Crowdin Enterprise API)
         */
        $response = $this->client->request('GET', sprintf('/projects/%d/files/%d/download', $this->projectId, $fileId));

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to download source file %d.', $fileId), $response);
        }

        $export = json_decode($response->getContent(), true);

        $exportResponse = $this->client->request('GET', $export['data']['url']);

        if (200 !== $exportResponse->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to download source file %d content.', $fileId), $exportResponse);
        }

        return $exportResponse->getContent();
    }

    private function listStrings(int $fileId, int $limit, int $offset): array
    {
        /**
         * @see https://support.crowdin.com/api/v2/#operation/api.projects.strings.getMany (Crowdin API)
         * @see https://support.crowdin.com/enterprise/api/#operation/api.projects.strings.getMany (Crowdin Enterprise API)
         */
        $response = $this->client->request('GET', sprintf('/projects/%d/strings', $this->projectId), [
            'query' => [
                'fileId' => $fileId,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to list strings for file %d in project %d. Message: "%s".', $fileId, $this->projectId, $response->getContent()), $response);
        }

        return json_decode($response->getContent(), true)['data'];
    }

    private function deleteString(int $stringId): void
    {
        /**
         * @see https://support.crowdin.com/api/v2/#operation/api.projects.strings.delete (Crowdin API)
         * @see https://support.crowdin.com/enterprise/api/#operation/api.projects.strings.delete (Crowdin Enterprise API)
         */
        $response = $this->client->request('DELETE', sprintf('/projects/%d/strings/%d', $this->projectId, $stringId));

        if (404 === $response->getStatusCode()) {
            throw new ProviderException(sprintf('String ID %d Not Found for the project %d.', $stringId, $this->projectId), $response);
        }

        if (204 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to delete string %d in project %d. Message: "%s".', $stringId, $this->projectId, $response->getContent()), $response);
        }
    }

    private function addStorage(string $domain, string $content): int
    {
        /**
         * @see https://support.crowdin.com/api/v2/#operation/api.storages.post (Crowdin API)
         * @see https://support.crowdin.com/enterprise/api/#operation/api.storages.post (Crowdin Enterprise API)
         */
        $response = $this->client->request('POST', '/storages', [
            'headers' => [
                'Crowdin-API-FileName' => urlencode(sprintf('%s.%s', $domain, 'xlf')),
                'Content-Type' => 'application/octet-stream',
            ],
            'body' => $content,
        ]);

        if (201 !== $response->getStatusCode()) {
            throw new ProviderException(sprintf('Unable to add a Storage in Crowdin for domain "%s".', $domain), $response);
        }

        $storage = json_decode($response->getContent(), true);

        return $storage['data']['id'];
    }

    private function getFilesList(): array
    {
        /**
         * @see https://support.crowdin.com/api/v2/#operation/api.projects.files.getMany (Crowdin API)
         * @see https://support.crowdin.com/enterprise/api/#operation/api.projects.files.getMany (Crowdin Enterprise API)
         */
        $response = $this->client->request('GET', sprintf('/projects/%d/files', $this->projectId), [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException('Unable to list Crowdin files.', $response);
        }

        return json_decode($response->getContent(), true)['data'];
    }
}
