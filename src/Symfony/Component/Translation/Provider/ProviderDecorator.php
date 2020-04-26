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

use Symfony\Component\Translation\TranslatorBag;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * @experimental in 5.3
 */
class ProviderDecorator implements ProviderInterface
{
    private $provider;
    private $locales;
    private $domains;

    public function __construct(ProviderInterface $provider, array $locales, array $domains = [])
    {
        $this->provider = $provider;
        $this->locales = $locales;
        $this->domains = $domains;
    }

    public function __toString(): string
    {
        return $this->provider->__toString();
    }

    public function getName(): string
    {
        return $this->provider->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function write(TranslatorBag $translatorBag): void
    {
        $this->provider->write($translatorBag);
    }

    /**
     * {@inheritdoc}
     */
    public function read(array $domains, array $locales): TranslatorBag
    {
        $domains = $this->domains ? $domains : array_intersect($this->domains, $domains);
        $locales = array_intersect($this->locales, $locales);

        return $this->provider->read($domains, $locales);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(TranslatorBag $translatorBag): void
    {
        $this->provider->delete($translatorBag);
    }

    public function getDomains(): array
    {
        return $this->domains;
    }
}
