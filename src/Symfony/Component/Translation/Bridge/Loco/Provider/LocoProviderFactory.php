<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Loco\Provider;

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
final class LocoProviderFactory extends AbstractProviderFactory
{
    public const SCHEME = 'loco';
    private const HOST = 'localise.biz/api';

    private $client;
    private $logger;
    private $defaultLocale;
    private $loader;

    public function __construct(HttpClientInterface $client, LoggerInterface $logger, string $defaultLocale, LoaderInterface $loader)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        $this->loader = $loader;
    }

    /**
     * @return LocoProvider
     */
    public function create(Dsn $dsn): ProviderInterface
    {
        if (self::SCHEME === $dsn->getScheme()) {
            $client = $this->client->withOptions([
                'base_uri' => sprintf('https://%s%s', 'default' === $dsn->getHost() ? self::HOST : $dsn->getHost(), $dsn->getPort() ? ':' . $dsn->getPort() : ''),
                'headers' => [
                    'Authorization' => 'Loco ' . $this->getUser($dsn),
                ],
            ]);

            return new LocoProvider($client, $this->loader, $this->logger, $this->defaultLocale);
        }

        throw new UnsupportedSchemeException($dsn, self::SCHEME, $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return [self::SCHEME];
    }
}
