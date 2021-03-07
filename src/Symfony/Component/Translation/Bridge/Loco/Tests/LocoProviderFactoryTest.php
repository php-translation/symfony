<?php

namespace Symfony\Component\Translation\Bridge\Loco\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Bridge\Loco\Provider\LocoProviderFactory;
use Symfony\Component\Translation\Exception\IncompleteDsnException;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LocoProviderFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = $this->createFactory();

        $provider = $factory->create(Dsn::fromString('loco://API_KEY@default'));

        $this->assertSame('loco://localise.biz/api', (string) $provider);
    }

    public function testCreateWithNoApiKeyThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);
        $factory->create(Dsn::fromString('loco://default'));
    }

    public function testSupportsReturnsTrueWithSupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('loco://API_KEY@default')));
    }

    public function testSupportsReturnsFalseWithUnsupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://API_KEY@default')));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeException()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);
        $factory->create(Dsn::fromString('somethingElse://API_KEY@default'));
    }

    private function createFactory(): LocoProviderFactory
    {
        return new LocoProviderFactory($this->createMock(HttpClientInterface::class), $this->createMock(LoggerInterface::class),'en', $this->createMock(LoaderInterface::class));
    }
}
