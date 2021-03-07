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
use Symfony\Component\Translation\TranslatorBagInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * @experimental in 5.3
 *
 * The class is used to filter domains and locales between the Translator config values and those specific to each provider.
 */
class FilteringProvider implements ProviderInterface
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

    public function getName(): string
    {
        return $this->provider->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function write(TranslatorBagInterface $translatorBag): void
    {
        $this->provider->write($translatorBag);
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $domains = !$this->domains ? $domains : array_intersect($this->domains, $domains);
        $locales = array_intersect($this->locales, $locales);

        return $this->provider->read($domains, $locales);
    }

    public function delete(TranslatorBag $translatorBag): void
    {
        $this->provider->delete($translatorBag);
    }

    public function getDomains(): array
    {
        return $this->domains;
    }
}
