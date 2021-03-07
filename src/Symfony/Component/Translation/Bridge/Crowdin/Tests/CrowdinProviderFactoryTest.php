<?php

namespace Symfony\Component\Translation\Bridge\Crowdin\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Bridge\Crowdin\Provider\CrowdinProviderFactory;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Exception\IncompleteDsnException;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CrowdinProviderFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = $this->createFactory();

        $provider = $factory->create(Dsn::fromString('crowdin://PROJECT_ID:API_TOKEN@default'));

        $this->assertSame('crowdin://api.crowdin.com/api/v2', (string) $provider);
    }

    public function testCreateWithOrganizationDsn()
    {
        $factory = $this->createFactory();

        $provider = $factory->create(Dsn::fromString('crowdin://PROJECT_ID:API_TOKEN@default?domain=ORGANIZATION_DOMAIN'));

        $this->assertSame('crowdin://ORGANIZATION_DOMAIN.api.crowdin.com/api/v2', (string) $provider);
    }

    public function testCreateWithNoApiKeyThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);
        $factory->create(Dsn::fromString('crowdin://default'));
    }

    public function testSupportsReturnsTrueWithSupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('crowdin://PROJECT_ID:API_TOKEN@default')));
    }

    public function testSupportsReturnsTrueWithSupportedSchemeEnterprise()
    {
        $factory = $this->createFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('crowdin://PROJECT_ID:API_TOKEN@default?domain=ORGANIZATION_DOMAIN')));
    }

    public function testSupportsReturnsFalseWithUnsupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://PROJECT_ID:API_TOKEN@default')));
    }

    public function testSupportsReturnsFalseWithUnsupportedSchemeEnterprise()
    {
        $factory = $this->createFactory();

        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://PROJECT_ID:API_TOKEN@default?domain=ORGANIZATION_DOMAIN')));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeException()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);
        $factory->create(Dsn::fromString('somethingElse://PROJECT_ID:API_TOKEN@default'));
    }

    private function createFactory(): CrowdinProviderFactory
    {
        return new CrowdinProviderFactory($this->createMock(HttpClientInterface::class), $this->createMock(LoggerInterface::class),'en', $this->createMock(LoaderInterface::class), $this->createMock(XliffFileDumper::class));
    }
}
