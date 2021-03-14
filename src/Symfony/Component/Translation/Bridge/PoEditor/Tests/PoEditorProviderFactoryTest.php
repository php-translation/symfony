<?php

namespace Symfony\Component\Translation\Bridge\PoEditor\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Bridge\Lokalise\Provider\LokaliseProviderFactory;
use Symfony\Component\Translation\Bridge\PoEditor\Provider\PoEditorProviderFactory;
use Symfony\Component\Translation\Exception\IncompleteDsnException;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\ProviderFactoryInterface;
use Symfony\Component\Translation\Tests\ProviderFactoryTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PoEditorProviderFactoryTest extends ProviderFactoryTestCase
{
    public function supportsProvider(): iterable
    {
        yield [true, 'poeditor://PROJECT_ID:API_KEY@default'];
        yield [false, 'somethingElse://PROJECT_ID:API_KEY@default'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://PROJECT_ID:API_KEY@default'];
    }

    public function createProvider(): iterable
    {
        yield [
            'poeditor://api.poeditor.com/v2',
            'poeditor://PROJECT_ID:API_KEY@default',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield ['poeditor://default'];
    }

    public function createFactory(): ProviderFactoryInterface
    {
        return new PoEditorProviderFactory($this->getClient(), $this->getLogger(), $this->getDefaultLocale(), $this->getLoader(), $this->getXliffFileDumper());
    }
}
