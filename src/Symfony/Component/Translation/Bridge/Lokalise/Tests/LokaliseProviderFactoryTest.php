<?php

namespace Symfony\Component\Translation\Bridge\Lokalise\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Bridge\Lokalise\Provider\LokaliseProviderFactory;
use Symfony\Component\Translation\Exception\IncompleteDsnException;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LokaliseProviderFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = $this->createFactory();

        $provider = $factory->create(Dsn::fromString('lokalise://PROJECT_ID:API_KEY@default'));

        $this->assertSame('lokalise://api.lokalise.com/api2', (string) $provider);
    }

    public function testCreateWithNoApiKeyThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);
        $factory->create(Dsn::fromString('lokalise://default'));
    }

    public function testSupportsReturnsTrueWithSupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('lokalise://PROJECT_ID:API_KEY@default')));
    }

    public function testSupportsReturnsFalseWithUnsupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://PROJECT_ID:API_KEY@default')));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeException()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);
        $factory->create(Dsn::fromString('somethingElse://PROJECT_ID:API_KEY@default'));
    }

    private function createFactory(): LokaliseProviderFactory
    {
        return new LokaliseProviderFactory($this->createMock(HttpClientInterface::class), $this->createMock(LoggerInterface::class),'en', $this->createMock(LoaderInterface::class));
    }
}
