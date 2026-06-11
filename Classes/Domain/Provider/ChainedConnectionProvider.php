<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Domain\Provider;

use SJS\Flow\MCP\Domain\Connection\Connection;
use SJS\Flow\MCP\Domain\Connection\ConnectionProviderInterface;
use Neos\Flow\Annotations as Flow;
use SJS\Neos\MCP\Domain\Provider\PersistentConnectionProvider;

class ChainedConnectionProvider implements ConnectionProviderInterface
{

    #[Flow\Inject(lazy: false)]
    protected PersistentConnectionProvider $persistentConnectionProvider;

    #[Flow\Inject(lazy: false)]
    protected OAuthConnectionProvider $oAuthConnectionProvider;

    public function getConnectionByTokenAndServerName(string $token, string $serverName): ?Connection
    {
        $connection = $this->oAuthConnectionProvider->getConnectionByTokenAndServerName($token, $serverName);
        if ($connection !== null) {
            return $connection;
        }

        $connection = $this->persistentConnectionProvider->getConnectionByTokenAndServerName($token, $serverName);
        if ($connection !== null) {
            return $connection;
        }

        return null;
    }
}