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
use Symfony\Component\Notifier\Transport\NullTransport;
use Symfony\Component\Translation\Provider\NullProvider;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * @experimental in 5.3
 */
class NullProviderTest extends TestCase
{
    public function testGetName()
    {
        $this->assertEquals('null', (new NullProvider())->getName());
    }
}
