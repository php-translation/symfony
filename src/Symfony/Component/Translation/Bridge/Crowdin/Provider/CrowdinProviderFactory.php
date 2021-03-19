<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Crowdin\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\AbstractProviderFactory;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Andrii Bodnar <andrii.bodnar@crowdin.com>
 *
 * @experimental in 5.3
 */
final class CrowdinProviderFactory extends AbstractProviderFactory
{
    public const SCHEME = 'crowdin';
    public const HOST = 'api.crowdin.com/api/v2/';
    private const DSN_OPTION_DOMAIN = 'domain';

    private $client;
    private $logger;
    private $defaultLocale;
    private $loader;
    private $xliffFileDumper;

    public function __construct(HttpClientInterface $client, LoggerInterface $logger, string $defaultLocale, LoaderInterface $loader, XliffFileDumper $xliffFileDumper)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
        $this->loader = $loader;
        $this->xliffFileDumper = $xliffFileDumper;
    }

    /**
     * @return CrowdinProvider
     */
    public function create(Dsn $dsn): ProviderInterface
    {
        if (self::SCHEME === $dsn->getScheme()) {
            if ($dsn->getOption(self::DSN_OPTION_DOMAIN)) {
                $host = sprintf('%s.%s', $dsn->getOption(self::DSN_OPTION_DOMAIN), self::HOST);
            } else {
                $host = self::HOST;
            }

            $endpoint = sprintf('%s%s', $host, $dsn->getPort() ? ':'.$dsn->getPort() : '');

            $client = $this->client->withOptions([
                'base_uri' => 'https://'.$endpoint,
                'headers' => [
                    'Authorization' => 'Bearer '.$this->getPassword($dsn),
                ],
            ]);

            return new CrowdinProvider($this->getUser($dsn), $client, $this->loader, $this->logger, $this->defaultLocale, $this->xliffFileDumper, $endpoint);
        }

        throw new UnsupportedSchemeException($dsn, self::SCHEME, $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return [self::SCHEME];
    }
}
