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

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\TranslationPullCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * @experimental in 5.3
 */
class TranslationPullCommandTest extends TestCase
{
    private $fs;
    private $translationDir;

    public function testPullNewMessages()
    {
        $this->markTestIncomplete();
    }

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
     * @return CommandTester
     */
    private function createCommandTester($extractedMessages = [], $loadedMessages = [], HttpKernel\KernelInterface $kernel = null, array $transPaths = [], array $viewsPaths = [])
    {
        $this->markTestIncomplete();

        $translator = $this->getMockBuilder(\Symfony\Component\Translation\Translator::class)
            ->disableOriginalConstructor()
            ->getMock();

        $translator
            ->expects($this->any())
            ->method('getFallbackLocales')
            ->willReturn(['en']);

        $loader = $this->getMockBuilder(\Symfony\Component\Translation\Reader\TranslationReader::class)->getMock();
        $loader
            ->expects($this->any())
            ->method('read')
            ->willReturnCallback(
                function ($path, $catalogue) use ($loadedMessages) {
                    $catalogue->add($loadedMessages);
                }
            );

        $writer = $this->getMockBuilder(\Symfony\Component\Translation\Writer\TranslationWriter::class)->getMock();
        $writer
            ->expects($this->any())
            ->method('getFormats')
            ->willReturn(
                ['xlf', 'yml', 'yaml']
            );

        $providers = $this->getMockBuilder(\Symfony\Component\Translation\Provider\TranslationProviderCollection::class)->getMock();
        $providers
            ->expects($this->any())
            ->method('keys')
            ->willReturn(
                ['loco']
            );

        if (null === $kernel) {
            $returnValues = [
                ['foo', $this->getBundle($this->translationDir)],
                ['test', $this->getBundle('test')],
            ];
            $kernel = $this->getMockBuilder(\Symfony\Component\HttpKernel\KernelInterface::class)->getMock();
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

        $command = new TranslationPullCommand($providers, $writer, $loader, 'en', $this->translationDir.'/translations', $transPaths, ['en', 'fr', 'nl']);

        $application = new Application($kernel);
        $application->add($command);

        return new CommandTester($application->find('translation:pull'));
    }

    private function getBundle($path)
    {
        $bundle = $this->getMockBuilder(\Symfony\Component\HttpKernel\Bundle\BundleInterface::class)->getMock();
        $bundle
            ->expects($this->any())
            ->method('getPath')
            ->willReturn($path)
        ;

        return $bundle;
    }
}
