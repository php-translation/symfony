<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Provider;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Translation\Event\MessageEvent;
use Symfony\Component\Translation\Message\MessageInterface;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * @experimental in 5.3
 */
class NullProvider implements ProviderInterface
{
    public function __toString(): string
    {
        return NullProviderFactory::SCHEME . '://default';
    }

    public function write(TranslatorBagInterface $translatorBag, bool $override = false): void
    {
    }

    public function read(array $domains, array $locales): TranslatorBagInterface
    {
        return new TranslatorBag();
    }

    public function delete(TranslatorBagInterface $translatorBag): void
    {
    }

    public function getName(): string
    {
        return 'null';
    }
}
