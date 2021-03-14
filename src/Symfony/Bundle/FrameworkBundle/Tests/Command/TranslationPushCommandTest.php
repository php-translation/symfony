<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\TranslationPushCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\Bridge\Loco\LocoProvider;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;
use Symfony\Component\Translation\Reader\TranslationReader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\Writer\TranslationWriter;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
class TranslationPushCommandTest extends TestCase
{
    private $fs;
    private $translationDir;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf_translation', true);
        $this->fs->mkdir($this->translationDir.'/translations');
        $this->fs->mkdir($this->translationDir.'/templates');
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->translationDir);
    }

    /**
     * @dataProvider providersProvider
     */
    public function testPushNewMessages($providers)
    {
        $this->markTestIncomplete();

        $tester = $this->createCommandTester(
            ['messages' => ['new.foo' => 'newFoo']],
            ['messages' => ['old.foo' => 'oldFoo']],
            $providers,
            ['en'],
            ['messages']
        );
        foreach ($providers as $name => $provider) {
            $tester->execute([
                'command' => 'translation:push',
                'provider' => $name,
            ]);
            $this->assertRegExp('/New local translations are sent to/', $tester->getDisplay());
        }
    }

    public function providersProvider(): \Generator
    {
        yield [
            ['loco' => $this->getMockBuilder(LocoProvider::class)->disableOriginalConstructor()->getMock()],
        ];
    }

    /**
     * @return CommandTester
     */
    private function createCommandTester($providerMessages = [], $localMessages = [], array $providers = [], array $locales = [], array $domains = [], KernelInterface $kernel = null, array $transPaths = [])
    {
        $translator = $this->getMockBuilder(Translator::class)
            ->disableOriginalConstructor()
            ->getMock();

        $translator
            ->expects($this->any())
            ->method('getFallbackLocales')
            ->willReturn(['en']);

        $reader = $this->getMockBuilder(TranslationReader::class)->getMock();
        $reader
            ->expects($this->any())
            ->method('read')
            ->willReturnCallback(
                function ($path, $catalogue) use ($localMessages) {
                    $catalogue->add($localMessages);
                }
            );

        $writer = $this->getMockBuilder(TranslationWriter::class)->getMock();
        $writer
            ->expects($this->any())
            ->method('getFormats')
            ->willReturn(
                ['xlf', 'yml', 'yaml']
            );

        $providersMock = $this->getMockBuilder(TranslationProviderCollection::class)
            ->setConstructorArgs([$providers])
            ->getMock();

        /** @var MockObject $provider */
        foreach ($providers as $name => $provider) {
            $provider
                ->expects($this->any())
                ->method('read')
                ->willReturnCallback(
                    function (array $domains, array $locales) use ($providerMessages) {
                        $translatorBag = new TranslatorBag();
                        foreach ($locales as $locale) {
                            foreach ($domains as $domain) {
                                $translatorBag->addCatalogue((new MessageCatalogue($locale, $providerMessages)), $domain);
                            }
                        }

                        return $translatorBag;
                    }
                );

            $providersMock
                ->expects($this->once())
                ->method('get')->with($name)
                ->willReturnReference($provider);
        }

        if (null === $kernel) {
            $returnValues = [
                ['foo', $this->getBundle($this->translationDir)],
                ['test', $this->getBundle('test')],
            ];
            $kernel = $this->getMockBuilder(KernelInterface::class)->getMock();
            $kernel
                ->expects($this->any())
                ->method('getBundle')
                ->willReturnMap($returnValues);
        }

        $kernel
            ->expects($this->any())
            ->method('getBundles')
            ->willReturn([]);

        $container = new Container();
        $kernel
            ->expects($this->any())
            ->method('getContainer')
            ->willReturn($container);

        $command = new TranslationPushCommand($providersMock, $reader, $this->translationDir.'/translations', $transPaths, ['en', 'fr', 'nl']);

        $application = new Application($kernel);
        $application->add($command);

        return new CommandTester($application->find('translation:push'));
    }

    private function getBundle($path)
    {
        $bundle = $this->getMockBuilder(BundleInterface::class)->getMock();
        $bundle
            ->expects($this->any())
            ->method('getPath')
            ->willReturn($path)
        ;

        return $bundle;
    }
}
