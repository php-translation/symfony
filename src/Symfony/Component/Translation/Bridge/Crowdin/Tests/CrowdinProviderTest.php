<?php

namespace Symfony\Component\Translation\Bridge\Crowdin\Tests;

use Generator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Bridge\Crowdin\Provider\CrowdinProvider;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CrowdinProviderTest extends TestCase
{
    public function toStringDataProvider(): Generator
    {
        $loader = $this->createMock(LoaderInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $defaultLocale = 'en';
        $xliffDumper = $this->createMock(XliffFileDumper::class);

        yield [
            new CrowdinProvider('PROJECT_ID', $this->createMock(HttpClientInterface::class)->withOptions([
                'base_uri' => 'api.crowdin.com/api/v2',
                'headers' => [
                    'Authorization' => 'Bearer API_TOKEN',
                ],
            ]), $loader, $logger, $defaultLocale, $xliffDumper),
            'crowdin://api.crowdin.com/api/v2',
        ];

        yield [
            new CrowdinProvider('PROJECT_ID', $this->createMock(HttpClientInterface::class)->withOptions([
                'base_uri' => 'example.com:19',
                'headers' => [
                    'Authorization' => 'Bearer API_TOKEN',
                ],
            ]), $loader, $logger, $defaultLocale, $xliffDumper),
            'crowdin://example.com:19',
        ];

        yield [
            new CrowdinProvider('PROJECT_ID', $this->createMock(HttpClientInterface::class)->withOptions([
                'base_uri' => 'organization.api.crowdin.com/api/v2',
                'headers' => [
                    'Authorization' => 'Bearer API_TOKEN',
                ],
            ]), $loader, $logger, $defaultLocale, $xliffDumper),
            'crowdin://organization.api.crowdin.com/api/v2',
        ];
    }

    /**
     * @dataProvider toStringDataProvider
     */
    public function testToString(CrowdinProvider $provider, string $expected)
    {
        $this->assertSame($expected, (string) $provider);
    }

    public function testGetName()
    {
        $client = $this->createMock(HttpClientInterface::class)->withOptions([
            'base_uri' => 'api.crowdin.com/api/v2',
            'headers' => [
                'Authorization' => 'Bearer API_TOKEN',
            ],
        ]);

        $this->assertSame('crowdin', (new CrowdinProvider('PROJECT_ID', $client))->getName());
    }
}
