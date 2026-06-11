<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Domain\ValueObject;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use SJS\Neos\MCP\OAuth\Domain\Model\Client;

class LeagueClient implements ClientEntityInterface
{
    private Client $wrappedClient;

    public function __construct(Client $client)
    {
        $this->wrappedClient = $client;
    }

    public function getWrappedClient(): Client
    {
        return $this->wrappedClient;
    }

    public function getIdentifier(): string
    {
        return $this->wrappedClient->getClientId();
    }

    public function getName(): string
    {
        return $this->wrappedClient->getName();
    }

    /**
     * @return string|string[]
     */
    public function getRedirectUri()
    {
        $uris = $this->wrappedClient->getRedirectUris();
        if (\count($uris) === 1) {
            return $uris[0];
        }
        return $uris;
    }

    public function isConfidential(): bool
    {
        return $this->wrappedClient->getClientSecretHash() !== '';
    }
}
