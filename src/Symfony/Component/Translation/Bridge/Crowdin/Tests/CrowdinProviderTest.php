<?php

namespace Symfony\Component\Translation\Bridge\Crowdin\Tests;

use Generator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Translation\Bridge\Crowdin\Provider\CrowdinProvider;

class CrowdinProviderTest extends TestCase
{
    public function toStringDataProvider(): Generator
    {
        yield [
            new CrowdinProvider('PROJECT_ID', 'API_TOKEN'),
            'crowdin://api.crowdin.com/api/v2',
        ];

        yield [
            (new CrowdinProvider('PROJECT_ID', 'API_TOKEN'))->setHost('crowdin.com'),
            'crowdin://crowdin.com',
        ];

        yield [
            (new CrowdinProvider('PROJECT_ID', 'API_TOKEN'))->setHost('example.com')->setPort(19),
            'crowdin://example.com:19',
        ];

        yield [
            (new CrowdinProvider('PROJECT_ID', 'API_TOKEN', 'organization')),
            'crowdin://organization.api.crowdin.com/api/v2',
        ];
    }

    /**
     * @dataProvider toStringDataProvider
     * @param CrowdinProvider $provider
     * @param string $expected
     */
    public function testToString(CrowdinProvider $provider, string $expected): void
    {
        $this->assertSame($expected, (string) $provider);
    }

    public function testGetName(): void
    {
        $this->assertSame('crowdin', (new CrowdinProvider('PROJECT_ID', 'API_TOKEN'))->getName());
    }

    public function getDefaultHostDataProvider(): Generator
    {
        yield [
            new CrowdinProvider('PROJECT_ID', 'API_TOKEN', 'ORGANIZATION_DOMAIN'),
            'ORGANIZATION_DOMAIN.api.crowdin.com/api/v2',
        ];

        yield [
            new CrowdinProvider('PROJECT_ID', 'API_TOKEN'),
            'api.crowdin.com/api/v2',
        ];
    }

    /**
     * @dataProvider getDefaultHostDataProvider
     * @param CrowdinProvider $provider
     * @param string $expected
     * @throws ReflectionException
     */
    public function testGetDefaultHost(CrowdinProvider $provider, string $expected): void
    {
        $reflection = new ReflectionClass(get_class($provider));
        $method = $reflection->getMethod('getDefaultHost');
        $method->setAccessible(true);

        $actualResult = $method->invokeArgs($provider, []);

        $this->assertSame($expected, $actualResult);
    }

    public function testGetDefaultHeaders(): void
    {
        $provider = new CrowdinProvider('PROJECT_ID', 'API_TOKEN');

        $reflection = new ReflectionClass(get_class($provider));
        $method = $reflection->getMethod('getDefaultHeaders');
        $method->setAccessible(true);

        $actualResult = $method->invokeArgs($provider, []);

        $this->assertSame(['Authorization' => 'Bearer API_TOKEN'], $actualResult);
    }
}
