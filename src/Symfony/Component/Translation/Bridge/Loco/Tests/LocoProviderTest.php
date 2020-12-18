<?php

namespace Symfony\Component\Translation\Bridge\Loco\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Translation\Bridge\Loco\Provider\LocoProvider;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Contracts\HttpClient\ResponseInterface;

class LocoProviderTest extends TestCase
{
    /**
     * @dataProvider getProviderData
     */
    public function testToString(LocoProvider $provider, string $expected)
    {
        $this->assertSame($expected, (string) $provider);
    }

    public function testGetName()
    {
        $this->assertSame('loco', (new LocoProvider('API_KEY'))->getName());
    }

    public function testCompleteWriteProcess()
    {
        $createAssetResponse = $this->createMock(ResponseInterface::class);
        $createAssetResponse->expects($this->exactly(4))
            ->method('getStatusCode')
            ->willReturn(201);
        $translateAssetResponse = $this->createMock(ResponseInterface::class);
        $translateAssetResponse->expects($this->exactly(8))
            ->method('getStatusCode')
            ->willReturn(200);
        $getTagsEmptyResponse = $this->createMock(ResponseInterface::class);
        $getTagsEmptyResponse->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $getTagsEmptyResponse->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn('[]');
        $getTagsNotEmptyResponse = $this->createMock(ResponseInterface::class);
        $getTagsNotEmptyResponse->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $getTagsNotEmptyResponse->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn('["messages"]');
        $createTagResponse = $this->createMock(ResponseInterface::class);
        $createTagResponse->expects($this->exactly(4))
            ->method('getStatusCode')
            ->willReturn(201);
        $tagAssetResponse = $this->createMock(ResponseInterface::class);
        $tagAssetResponse->expects($this->exactly(4))
            ->method('getStatusCode')
            ->willReturn(200);

        $expectedAuthHeader = 'Authorization: Loco API_KEY';

        $responses = [
            'createAsset1' => function (string $method, string $url, array $options = []) use ($createAssetResponse, $expectedAuthHeader): ResponseInterface {
                $expectedBody = http_build_query([
                    'name' => 'a',
                    'id' => 'a',
                    'type' => 'text',
                    'default' => 'untranslated',
                ]);

                $this->assertEquals('POST', $method);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals($expectedBody, $options['body']);

                return $createAssetResponse;
            },
            'getTags1' => function (string $method, string $url, array $options = []) use ($getTagsEmptyResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('GET', $method);
                $this->assertEquals('https://localise.biz/api/tags.json', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return $getTagsEmptyResponse;
            },
            'createTag1' => function (string $method, string $url, array $options = []) use ($createTagResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('POST', $method);
                $this->assertEquals('https://localise.biz/api/tags.json', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals(http_build_query(['name' => 'messages']), $options['body']);

                return $createTagResponse;
            },
            'tagAsset1' => function (string $method, string $url, array $options = []) use ($tagAssetResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('POST', $method);
                $this->assertEquals('https://localise.biz/api/tags/messages.json', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals('a', $options['body']);

                return $tagAssetResponse;
            },
            'createAsset2' => function (string $method, string $url, array $options = []) use ($createAssetResponse, $expectedAuthHeader): ResponseInterface {
                $expectedBody = http_build_query([
                    'name' => 'post.num_comments',
                    'id' => 'post.num_comments',
                    'type' => 'text',
                    'default' => 'untranslated',
                ]);

                $this->assertEquals('POST', $method);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals($expectedBody, $options['body']);

                return $createAssetResponse;
            },
            'getTags2' => function (string $method, string $url, array $options = []) use ($getTagsNotEmptyResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('GET', $method);
                $this->assertEquals('https://localise.biz/api/tags.json', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);

                return $getTagsNotEmptyResponse;
            },
            'createTag2' => function (string $method, string $url, array $options = []) use ($createTagResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('POST', $method);
                $this->assertEquals('https://localise.biz/api/tags.json', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals(http_build_query(['name' => 'validators']), $options['body']);

                return $createTagResponse;
            },
            'tagAsset2' => function (string $method, string $url, array $options = []) use ($tagAssetResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('POST', $method);
                $this->assertEquals('https://localise.biz/api/tags/validators.json', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals('post.num_comments', $options['body']);

                return $tagAssetResponse;
            },

            'translateAsset1' => function (string $method, string $url, array $options = []) use ($translateAssetResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('POST', $method);
                $this->assertEquals('https://localise.biz/api/translations/a/en', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals('trans_en_a', $options['body']);

                return $translateAssetResponse;
            },
            'translateAsset2' => function (string $method, string $url, array $options = []) use ($translateAssetResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('POST', $method);
                $this->assertEquals('https://localise.biz/api/translations/post.num_comments/en', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals('{count, plural, one {# comment} other {# comments}}', $options['body']);

                return $translateAssetResponse;
            },
            'translateAsset3' => function (string $method, string $url, array $options = []) use ($translateAssetResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('POST', $method);
                $this->assertEquals('https://localise.biz/api/translations/a/fr', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals('trans_fr_a', $options['body']);

                return $translateAssetResponse;
            },
            'translateAsset4' => function (string $method, string $url, array $options = []) use ($translateAssetResponse, $expectedAuthHeader): ResponseInterface {
                $this->assertEquals('POST', $method);
                $this->assertEquals('https://localise.biz/api/translations/post.num_comments/fr', $url);
                $this->assertEquals($expectedAuthHeader, $options['normalized_headers']['authorization'][0]);
                $this->assertEquals('{count, plural, one {# commentaire} other {# commentaires}}', $options['body']);

                return $translateAssetResponse;
            },
        ];

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'messages' => ['a' => 'trans_en_a'],
            'validators' => ['post.num_comments' => '{count, plural, one {# comment} other {# comments}}'],
        ]));
        $translatorBag->addCatalogue(new MessageCatalogue('fr', [
            'messages' => ['a' => 'trans_fr_a'],
            'validators' => ['post.num_comments' => '{count, plural, one {# commentaire} other {# commentaires}}'],
        ]));

        $locoProvider = new LocoProvider('API_KEY', new MockHttpClient($responses), new XliffFileLoader(), $this->createMock(LoggerInterface::class), 'en');
        $locoProvider->write($translatorBag);
    }

    /**
     * @dataProvider getLocoResponsesForOneLocaleAndOneDomain
     */
    public function testReadForOneLocaleAndOneDomain(string $locale, string $domain, string $responseContent, array $expectedMessages)
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn($responseContent);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $locale, $domain): ResponseInterface {
            $this->assertEquals('GET', $method);
            $this->assertEquals('Authorization: Loco API_KEY', $options['normalized_headers']['authorization'][0]);
            $this->assertEquals("https://localise.biz/api/export/locale/{$locale}.xlf?filter={$domain}", $url);

            return $response;
        });

        $locoProvider = new LocoProvider('API_KEY', $httpClient, new XliffFileLoader(), $this->createMock(LoggerInterface::class), 'en');
        $translatorBag = $locoProvider->read([$domain], [$locale]);

        $arrayLoader = new ArrayLoader();
        $expectedTranslatorBag = new TranslatorBag();
        $expectedTranslatorBag->addCatalogue($arrayLoader->load($expectedMessages, $locale, $domain));

        $this->assertEquals($expectedTranslatorBag->all(), $translatorBag->all());
    }

    /**
     * @dataProvider getLocoResponsesForManyLocalesAndManyDomains
     */
    public function testReadForManyLocalesAndManyDomains(array $locales, array $domains, array $responseContents, array $expectedMessages)
    {
        foreach ($locales as $locale) {
            $response = $this->createMock(ResponseInterface::class);
            $response->expects($this->once())
                ->method('getContent')
                ->willReturn($responseContents[$locale]);
            $response->expects($this->exactly(2))
                ->method('getStatusCode')
                ->willReturn(200);

            $httpClient = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $locale, $domains): ResponseInterface {
                $filter = implode(',', $domains);

                $this->assertEquals('GET', $method);
                $this->assertEquals('Authorization: Loco API_KEY', $options['normalized_headers']['authorization'][0]);
                $this->assertEquals("https://localise.biz/api/export/locale/{$locale}.xlf?filter={$filter}", $url);

                return $response;
            });

            $locoProvider = new LocoProvider('API_KEY', $httpClient, new XliffFileLoader(), $this->createMock(LoggerInterface::class), 'en');
            $translatorBag = $locoProvider->read($domains, [$locale]);

            $arrayLoader = new ArrayLoader();
            $expectedTranslatorBag = new TranslatorBag();
            foreach ($domains as $domain) {
                $expectedTranslatorBag->addCatalogue($arrayLoader->load($expectedMessages[$locale], $locale, $domain));
            }

            $this->assertEquals($expectedTranslatorBag->all(), $translatorBag->all());
        }
    }

    public function getProviderData(): \Generator
    {
        yield [
            new LocoProvider('API_KEY'),
            'loco://localise.biz/api',
        ];

        yield [
            (new LocoProvider('API_KEY'))->setHost('example.com'),
            'loco://example.com',
        ];

        yield [
            (new LocoProvider('API_KEY'))->setHost('example.com')->setPort(99),
            'loco://example.com:99',
        ];
    }

    public function getLocoResponsesForOneLocaleAndOneDomain(): \Generator
    {
        yield ['en', 'messages', <<<'XLIFF'
<?xml version="1.0" encoding="UTF-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
  <file original="https://localise.biz/user/symfony-translation-provider" source-language="en" datatype="database" tool-id="loco">
    <header>
      <tool tool-id="loco" tool-name="Loco" tool-version="1.0.25 20201211-1" tool-company="Loco"/>
    </header>
    <body>
      <trans-unit id="loco:5fd89b853ee27904dd6c5f67" resname="index.hello" datatype="plaintext">
        <source>index.hello</source>
        <target state="translated">Hello</target>
      </trans-unit>
      <trans-unit id="loco:5fd89b8542e5aa5cc27457e2" resname="index.greetings" datatype="plaintext" extradata="loco:format=icu">
        <source>index.greetings</source>
        <target state="translated">Welcome, {firstname}!</target>
      </trans-unit>
    </body>
  </file>
</xliff>

XLIFF,
            [
                'index.hello' => 'Hello',
                'index.greetings' => 'Welcome, {firstname}!',
            ],
        ];

        yield ['fr', 'messages', <<<'XLIFF'
<?xml version="1.0" encoding="UTF-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
  <file original="https://localise.biz/user/symfony-translation-provider" source-language="en" datatype="database" tool-id="loco">
    <header>
      <tool tool-id="loco" tool-name="Loco" tool-version="1.0.25 20201211-1" tool-company="Loco"/>
    </header>
    <body>
      <trans-unit id="loco:5fd89b853ee27904dd6c5f67" resname="index.hello" datatype="plaintext">
        <source>index.hello</source>
        <target state="translated">Bonjour</target>
      </trans-unit>
      <trans-unit id="loco:5fd89b8542e5aa5cc27457e2" resname="index.greetings" datatype="plaintext" extradata="loco:format=icu">
        <source>index.greetings</source>
        <target state="translated">Bienvenue, {firstname} !</target>
      </trans-unit>
    </body>
  </file>
</xliff>

XLIFF,
            [
                'index.hello' => 'Bonjour',
                'index.greetings' => 'Bienvenue, {firstname} !',
            ],
        ];
    }

    public function getLocoResponsesForManyLocalesAndManyDomains(): \Generator
    {
        yield [
            ['en', 'fr'],
            ['messages', 'validators'],
            [
                'en' => <<<'XLIFF'
<?xml version="1.0" encoding="UTF-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
  <file original="https://localise.biz/user/symfony-translation-provider" source-language="en" datatype="database" tool-id="loco">
    <header>
      <tool tool-id="loco" tool-name="Loco" tool-version="1.0.25 20201211-1" tool-company="Loco"/>
    </header>
    <body>
      <trans-unit id="loco:5fd89b853ee27904dd6c5f67" resname="index.hello" datatype="plaintext">
        <source>index.hello</source>
        <target state="translated">Hello</target>
      </trans-unit>
      <trans-unit id="loco:5fd89b8542e5aa5cc27457e2" resname="index.greetings" datatype="plaintext" extradata="loco:format=icu">
        <source>index.greetings</source>
        <target state="translated">Welcome, {firstname}!</target>
      </trans-unit>
    </body>
  </file>
</xliff>

XLIFF,
                'fr' => <<<'XLIFF'
<?xml version="1.0" encoding="UTF-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 http://docs.oasis-open.org/xliff/v1.2/os/xliff-core-1.2-strict.xsd">
  <file original="https://localise.biz/user/symfony-translation-provider" source-language="en" datatype="database" tool-id="loco">
    <header>
      <tool tool-id="loco" tool-name="Loco" tool-version="1.0.25 20201211-1" tool-company="Loco"/>
    </header>
    <body>
      <trans-unit id="loco:5fd89b853ee27904dd6c5f67" resname="index.hello" datatype="plaintext">
        <source>index.hello</source>
        <target state="translated">Bonjour</target>
      </trans-unit>
      <trans-unit id="loco:5fd89b8542e5aa5cc27457e2" resname="index.greetings" datatype="plaintext" extradata="loco:format=icu">
        <source>index.greetings</source>
        <target state="translated">Bienvenue, {firstname} !</target>
      </trans-unit>
    </body>
  </file>
</xliff>

XLIFF,
            ],
            [
                'en' => [
                    'index.hello' => 'Hello',
                    'index.greetings' => 'Welcome, {firstname}!',
                ],
                'fr' => [
                    'index.hello' => 'Bonjour',
                    'index.greetings' => 'Bienvenue, {firstname} !',
                ],
            ],
        ];
    }
}
