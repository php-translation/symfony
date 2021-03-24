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

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Exception\IncompleteDsnException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractProviderFactory implements ProviderFactoryInterface
{
    public function supports(Dsn $dsn): bool
    {
        return \in_array($dsn->getScheme(), $this->getSupportedSchemes());
    }

    /**
     * @return string[]
     */
    abstract protected function getSupportedSchemes(): array;

    protected function getUser(Dsn $dsn): string
    {
        $user = $dsn->getUser();
        if (null === $user) {
            throw new IncompleteDsnException('User is not set.', $dsn->getOriginalDsn());
        }

        return $user;
    }

    protected function getPassword(Dsn $dsn): string
    {
        $password = $dsn->getPassword();
        if (null === $password) {
            throw new IncompleteDsnException('Password is not set.', $dsn->getOriginalDsn());
        }

        return $password;
    }
}
