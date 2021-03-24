<?php

namespace Symfony\Component\Translation\Bridge\Crowdin\Tests;

use Symfony\Component\Translation\Bridge\Crowdin\Provider\CrowdinProviderFactory;
use Symfony\Component\Translation\Provider\ProviderFactoryInterface;
use Symfony\Component\Translation\Tests\ProviderFactoryTestCase;

class CrowdinProviderFactoryTest extends ProviderFactoryTestCase
{
    public function supportsProvider(): iterable
    {
        yield [true, 'crowdin://PROJECT_ID:API_TOKEN@default'];
        yield [true, 'crowdin://PROJECT_ID:API_TOKEN@default?domain=ORGANIZATION_DOMAIN'];
        yield [false, 'somethingElse://PROJECT_ID:API_TOKEN@default'];
        yield [false, 'somethingElse://PROJECT_ID:API_TOKEN@default?domain=ORGANIZATION_DOMAIN'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://PROJECT_ID:API_TOKEN@default'];
        yield ['somethingElse://PROJECT_ID:API_TOKEN@default?domain=ORGANIZATION_DOMAIN'];
    }

    public function createProvider(): iterable
    {
        yield [
            'crowdin://api.crowdin.com/api/v2',
            'crowdin://PROJECT_ID:API_TOKEN@default',
        ];

        yield [
            'crowdin://ORGANIZATION_DOMAIN.api.crowdin.com/api/v2',
            'crowdin://PROJECT_ID:API_TOKEN@default?domain=ORGANIZATION_DOMAIN',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield ['crowdin://default'];
    }

    public function createFactory(): ProviderFactoryInterface
    {
        return new CrowdinProviderFactory($this->getClient(), $this->getLogger(), $this->getDefaultLocale(), $this->getLoader(), $this->getXliffFileDumper());
    }
}
