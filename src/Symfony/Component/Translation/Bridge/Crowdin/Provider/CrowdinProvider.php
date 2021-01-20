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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\AbstractProvider;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Andrii Bodnar <andrii.bodnar@crowdin.com>
 *
 * @experimental in 5.3
 *
 * In Crowdin:
 * Filenames refers to Symfony's translation domains;
 * Identifiers refers to Symfony's translation keys;
 * Translations refers to Symfony's translated messages
 */
final class CrowdinProvider extends AbstractProvider
{
    protected const HOST = 'api.crowdin.com/api/v2';

    /** @var string */
    private $apiToken;

    /** @var string */
    private $projectId;

    /** @var string */
    private $organizationDomain;

    private $xliffFileDumper;

    private $files = [];

    /** @var LoaderInterface|null */
    private $loader;

    /** @var LoggerInterface|null */
    private $logger;

    /** @var string|null */
    private $defaultLocale;

    public function __construct(string $projectId, string $apiToken, string $organizationDomain = null, HttpClientInterface $client = null, LoaderInterface $loader = null, LoggerInterface $logger = null, string $defaultLocale = null, XliffFileDumper $xliffFileDumper = null)
    {
        $this->projectId = $projectId;
        $this->apiToken = $apiToken;
        $this->organizationDomain = $organizationDomain;
        $this->loader = $loader;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        $this->xliffFileDumper = $xliffFileDumper;

        parent::__construct($client);
    }

    public function __toString(): string
    {
        return sprintf(CrowdinProviderFactory::SCHEME . '://%s', $this->getEndpoint());
    }

    public function getName(): string
    {
        return CrowdinProviderFactory::SCHEME;
    }

    /**
     * {@inheritdoc}
     */
    public function write(TranslatorBag $translatorBag): void
    {
        foreach($translatorBag->getDomains() as $domain) {
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

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
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

    protected function getDefaultHost(): string
    {
        if ($this->organizationDomain) {
            return sprintf('%s.%s', $this->organizationDomain, self::HOST);
        } else {
            return self::HOST;
        }
    }

    protected function getDefaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiToken
        ];
    }

    private function getFileId(string $domain): int
    {
        if (isset($this->files[$domain]) && $this->files[$domain]) {
            return $this->files[$domain];
        }

        $files = $this->getFilesList();

        foreach($files as $file) {
            if ($file['data']['name'] === sprintf('%s.%s', $domain, 'xlf')) {
                $this->files[$domain] = (int)$file['data']['id'];

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
        } while (count($strings) > 0);

        return $result;
    }

    private function addFile(string $domain, string $content): void
    {
        $storageId = $this->addStorage($domain, $content);

        /**
         * @see https://support.crowdin.com/api/v2/#operation/api.projects.files.getMany (Crowdin API)
         * @see https://support.crowdin.com/enterprise/api/#operation/api.projects.files.getMany (Crowdin Enterprise API)
         */
        $response = $this->client->request('POST', sprintf('https://%s/projects/%s/files', $this->getEndpoint(), $this->projectId), [
            'headers' => array_merge($this->getDefaultHeaders(), [
                'Content-Type' => 'application/json',
            ]),
            'body' => json_encode([
                'storageId' => $storageId,
                'name' => sprintf('%s.%s', $domain, 'xlf'),
            ]),
        ]);

        $responseContent = $response->getContent(false);

        if (Response::HTTP_CREATED !== $response->getStatusCode()) {
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
        $response = $this->client->request('PUT', sprintf('https://%s/projects/%s/files/%d', $this->getEndpoint(), $this->projectId, $fileId), [
            'headers' => array_merge($this->getDefaultHeaders(), [
                'Content-Type' => 'application/json',
            ]),
            'body' => json_encode([
                'storageId' => $storageId,
            ]),
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(
                sprintf('Unable to update file in Crowdin for file ID "%d" and domain "%s".', $fileId, $domain),
                $response
            );
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
        $response = $this->client->request('POST', sprintf('https://%s/projects/%s/translations/%s', $this->getEndpoint(), $this->projectId, $locale), [
            'headers' => array_merge($this->getDefaultHeaders(), [
                'Content-Type' => 'application/json',
            ]),
            'body' => json_encode([
                'storageId' => $storageId,
                'fileId' => $fileId,
            ]),
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(
                sprintf('Unable to upload translations to Crowdin for domain "%s" and locale "%s".', $domain, $locale),
                $response
            );
        }
    }

    private function exportProjectTranslations(string $languageId, int $fileId): string
    {
        /**
         * @see https://support.crowdin.com/api/v2/#operation/api.projects.translations.exports.post (Crowdin API)
         * @see https://support.crowdin.com/enterprise/api/#operation/api.projects.translations.exports.post (Crowdin Enterprise API)
         */
        $response = $this->client->request('POST', sprintf('https://%s/projects/%d/translations/exports', $this->getEndpoint(), $this->projectId), [
            'headers' => array_merge($this->getDefaultHeaders(), [
                'Content-Type' => 'application/json',
            ]),
            'body' => json_encode([
                'targetLanguageId' => $languageId,
                'fileIds' => [$fileId],
            ]),
        ]);

        if (Response::HTTP_NO_CONTENT === $response->getStatusCode()) {
            throw new ProviderException(
                sprintf('No content in exported file %d.', $fileId),
                $response
            );
        }

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(
                sprintf('Unable to export file %d translations for language %s.', $fileId, $languageId),
                $response
            );
        }

        $export = json_decode($response->getContent(), true);

        $exportResponse = $this->client->request('GET', $export['data']['url']);

        if (Response::HTTP_OK !== $exportResponse->getStatusCode()) {
            throw new ProviderException(
                sprintf('Unable to download file %d translations content for language %s.', $fileId, $languageId),
                $exportResponse
            );
        }

        return $exportResponse->getContent();
    }

    private function downloadSourceFile(int $fileId): string
    {
        /**
         * @see https://support.crowdin.com/api/v2/#operation/api.projects.files.download.get (Crowdin API)
         * @see https://support.crowdin.com/enterprise/api/#operation/api.projects.files.download.get (Crowdin Enterprise API)
         */
        $response = $this->client->request('GET', sprintf('https://%s/projects/%d/files/%d/download', $this->getEndpoint(), $this->projectId, $fileId), [
            'headers' => array_merge($this->getDefaultHeaders(), [
                'Content-Type' => 'application/json',
            ])
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(
                sprintf('Unable to download source file %d.', $fileId),
                $response
            );
        }

        $export = json_decode($response->getContent(), true);

        $exportResponse = $this->client->request('GET', $export['data']['url']);

        if (Response::HTTP_OK !== $exportResponse->getStatusCode()) {
            throw new ProviderException(
                sprintf('Unable to download source file %d content.', $fileId),
                $exportResponse
            );
        }

        return $exportResponse->getContent();
    }

    private function listStrings(int $fileId, int $limit, int $offset): array
    {
        /**
         * @see https://support.crowdin.com/api/v2/#operation/api.projects.strings.getMany (Crowdin API)
         * @see https://support.crowdin.com/enterprise/api/#operation/api.projects.strings.getMany (Crowdin Enterprise API)
         */
        $response = $this->client->request('GET', sprintf('https://%s/projects/%d/strings', $this->getEndpoint(), $this->projectId), [
            'headers' => $this->getDefaultHeaders(),
            'query' => [
                'fileId' => $fileId,
                'limit' => $limit,
                'offset' => $offset
            ],
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException(
                sprintf('Unable to list strings for file %d in project %d. Message: %s.', $fileId, $this->projectId, $response->getContent()),
                $response
            );
        }

        return json_decode($response->getContent(), true)['data'];
    }

    private function deleteString(int $stringId): void
    {
        /**
         * @see https://support.crowdin.com/api/v2/#operation/api.projects.strings.delete (Crowdin API)
         * @see https://support.crowdin.com/enterprise/api/#operation/api.projects.strings.delete (Crowdin Enterprise API)
         */
        $response = $this->client->request('DELETE', sprintf('https://%s/projects/%d/strings/%d', $this->getEndpoint(), $this->projectId, $stringId), [
            'headers' => $this->getDefaultHeaders()
        ]);

        if (Response::HTTP_NOT_FOUND === $response->getStatusCode()) {
            throw new ProviderException(
                sprintf('String ID %d Not Found for the project %d.', $stringId, $this->projectId),
                $response
            );
        }

        if (Response::HTTP_NO_CONTENT !== $response->getStatusCode()) {
            throw new ProviderException(
                sprintf('Unable to delete string %d in project %d. Message: %s.', $stringId, $this->projectId, $response->getContent()),
                $response
            );
        }
    }

    private function addStorage(string $domain, string $content): int
    {
        /**
         * @see https://support.crowdin.com/api/v2/#operation/api.storages.post (Crowdin API)
         * @see https://support.crowdin.com/enterprise/api/#operation/api.storages.post (Crowdin Enterprise API)
         */
        $response = $this->client->request('POST', sprintf('https://%s/storages', $this->getEndpoint()), [
            'headers' => array_merge($this->getDefaultHeaders(), [
                'Crowdin-API-FileName' => urlencode(sprintf('%s.%s', $domain, 'xlf')),
                'Content-Type' => 'application/octet-stream',
            ]),
            'body' => $content,
        ]);

        if (Response::HTTP_CREATED !== $response->getStatusCode()) {
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
        $response = $this->client->request('GET', sprintf('https://%s/projects/%d/files', $this->getEndpoint(), $this->projectId), [
            'headers' => array_merge($this->getDefaultHeaders(), [
                'Content-Type' => 'application/json',
            ]),
        ]);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new ProviderException('Unable to list Crowdin files.', $response);
        }

        return json_decode($response->getContent(), true)['data'];
    }
}
