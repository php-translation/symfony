<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Provider;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\NullProvider;
use Symfony\Component\Translation\Provider\NullProviderFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * @experimental in 5.3
 */
class NullProviderFactoryTest extends TestCase
{
    /**
     * @var NullProviderFactory
     */
    private $nullProviderFactory;

    protected function setUp(): void
    {
        $this->nullProviderFactory = new NullProviderFactory(
            $this->createMock(HttpClientInterface::class),
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testCreateThrowsUnsupportedSchemeException()
    {
        $this->expectException(UnsupportedSchemeException::class);

        $this->nullProviderFactory->create(new Dsn('foo', ''));
    }

    public function testCreate()
    {
        $this->assertInstanceOf(
            NullProvider::class,
            $this->nullProviderFactory->create(new Dsn('null', ''))
        );
    }
}
