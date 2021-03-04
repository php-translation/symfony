<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\PoEditor\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\AbstractProviderFactory;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * @experimental in 5.3
 */
final class PoEditorProviderFactory extends AbstractProviderFactory
{
    public const SCHEME = 'poeditor';
    private const HOST = 'api.poeditor.com/v2';

    private $client;
    private $logger;
    private $defaultLocale;
    private $loader;

    public function __construct(HttpClientInterface $client = null, LoggerInterface $logger = null, string $defaultLocale = null, LoaderInterface $loader = null)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        $this->loader = $loader;
    }

    /**
     * @return PhraseProvider
     */
    public function create(Dsn $dsn): ProviderInterface
    {
        if (self::SCHEME === $dsn->getScheme()) {
            $client = $this->client->withOptions([
                'base_uri' => sprintf('%s%s', 'default' === $dsn->getHost() ? self::HOST : $dsn->getHost(), $dsn->getPort() ? ':' . $dsn->getPort() : ''),
            ]);

            return new PoEditorProvider($this->getUser($dsn), $this->getPassword($dsn), $client, $this->loader, $this->logger, $this->defaultLocale);
        }

        throw new UnsupportedSchemeException($dsn, self::SCHEME, $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return [self::SCHEME];
    }
}
