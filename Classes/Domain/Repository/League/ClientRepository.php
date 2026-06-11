<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Domain\Repository\League;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Neos\Flow\Annotations as Flow;
use SJS\Neos\MCP\OAuth\Domain\Model\Client;
use SJS\Neos\MCP\OAuth\Domain\Repository\ClientRepository as DoctrineClientRepository;
use SJS\Neos\MCP\OAuth\Domain\ValueObject\LeagueClient;

class ClientRepository implements ClientRepositoryInterface
{
    #[Flow\Inject]
    protected DoctrineClientRepository $doctrineClientRepository;

    public function getClientEntity($clientIdentifier): ?ClientEntityInterface
    {
        $client = $this->doctrineClientRepository->findOneByClientId($clientIdentifier);
        if ($client === null || !$client->isEnabled()) {
            return null;
        }
        return new LeagueClient($client);
    }

    public function validateClient($clientIdentifier, $clientSecret, $grantType): bool
    {
        $client = $this->doctrineClientRepository->findOneByClientId($clientIdentifier);
        if ($client === null || !$client->isEnabled()) {
            return false;
        }

        if (!\in_array($grantType, $client->getGrants(), true)) {
            return false;
        }

        if ($grantType === 'client_credentials') {
            return $client->verifyClientSecret((string)$clientSecret);
        }

        if ($clientSecret !== null && $client->getClientSecretHash() !== '') {
            return $client->verifyClientSecret($clientSecret);
        }

        return $clientSecret === null && $client->getClientSecretHash() === '';
    }
}
