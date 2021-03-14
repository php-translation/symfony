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

use Symfony\Component\Translation\TranslatorBagInterface;

interface ProviderInterface
{
    public function __toString(): string;

    public function getName(): string;

    /**
     * Translations available in the MessageCatalogue only must be created.
     * Translations available in both the MessageCatalogue and on the provider
     * must be overwritten.
     * Translations available on the provider only must be kept.
     */
    public function write(TranslatorBagInterface $translatorBag): void;

    public function read(array $domains, array $locales): TranslatorBagInterface;

    public function delete(TranslatorBagInterface $translatorBag): void;
}
