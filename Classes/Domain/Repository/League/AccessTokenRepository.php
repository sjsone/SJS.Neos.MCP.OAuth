<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Domain\Repository\League;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\AccountRepository;
use Neos\Party\Domain\Repository\PartyRepository;
use SJS\Neos\MCP\Domain\Model\ConnectionData;
use SJS\Neos\MCP\Domain\Repository\ConnectionDataRepository;
use SJS\Neos\MCP\OAuth\Domain\Model\AccessToken;
use SJS\Neos\MCP\OAuth\Domain\Repository\AccessTokenRepository as DoctrineAccessTokenRepository;
use SJS\Neos\MCP\OAuth\Domain\ValueObject\LeagueAccessToken;
use SJS\Neos\MCP\OAuth\Domain\ValueObject\LeagueScope;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    #[Flow\Inject]
    protected DoctrineAccessTokenRepository $doctrineTokenRepo;

    #[Flow\Inject]
    protected ConnectionDataRepository $connectionDataRepository;

    #[Flow\Inject]
    protected AccountRepository $accountRepository;

    #[Flow\Inject]
    protected PartyRepository $partyRepository;

    /**
     * Maps OAuth scope identifiers to Neos role identifiers.
     * @var array<string, array<string>>
     */
    protected array $scopeToRoleMapping = [
        'neos/editor' => ['Neos.Neos:Editor'],
        'neos/admin' => ['Neos.Neos:Administrator'],
    ];

    /**
     * @param ClientEntityInterface $clientEntity
     * @param ScopeEntityInterface[] $scopes
     * @param mixed $userIdentifier
     */
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null): AccessTokenEntityInterface
    {
        $token = new LeagueAccessToken();
        $token->setClient($clientEntity);
        $token->setUserIdentifier($userIdentifier);
        foreach ($scopes as $scope) {
            $token->addScope($scope);
        }
        return $token;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $client = $accessTokenEntity->getClient();
        if (!\method_exists($client, 'getWrappedClient')) {
            return;
        }

        $doctrineEntity = new AccessToken();
        $doctrineEntity->setClient($client->getWrappedClient());
        $doctrineEntity->setIdentifier($accessTokenEntity->getIdentifier());
        $doctrineEntity->setExpiryDateTime(\DateTime::createFromImmutable($accessTokenEntity->getExpiryDateTime()));
        $doctrineEntity->setUserIdentifier($accessTokenEntity->getUserIdentifier() ? (string)$accessTokenEntity->getUserIdentifier() : null);
        $scopes = \array_map(fn(LeagueScope $s) => $s->getIdentifier(), $accessTokenEntity->getScopes());
        $doctrineEntity->setScopes($scopes);
        $this->doctrineTokenRepo->add($doctrineEntity);

        // Create a corresponding ConnectionData so this OAuth token is
        // resolved through the unified ConnectionData provider layer.
        $userIdentifier = $accessTokenEntity->getUserIdentifier();
        if ($userIdentifier === null) {
            return;
        }

        $account = $this->accountRepository->findByAccountIdentifierAndAuthenticationProviderName(
            (string)$userIdentifier,
            'Neos.Neos:Backend'
        );
        if ($account === null) {
            return;
        }

        $clientId = $client->getIdentifier();

        // Derive role restrictions from granted scopes.
        $roleIdentifiers = [];
        foreach ($scopes as $scope) {
            if (isset($this->scopeToRoleMapping[$scope])) {
                $roleIdentifiers = \array_merge($roleIdentifiers, $this->scopeToRoleMapping[$scope]);
            }
        }

        $party = $this->partyRepository->findOneHavingAccount($account);
        if ($party === null) {
            return;
        }

        $connectionData = new ConnectionData();
        $connectionData->setToken($accessTokenEntity->getIdentifier());
        $connectionData->setSourceIdentifier("oauth:{$clientId}");
        $connectionData->setName("OAuth: " . $client->getWrappedClient()->getName());
        $connectionData->setParty($party);
        $connectionData->setAccount($account);
        $connectionData->setOnlyAllowedRoleIdentifiers($roleIdentifiers);

        $this->connectionDataRepository->add($connectionData);
    }

    public function revokeAccessToken($tokenId): void
    {
        $token = $this->doctrineTokenRepo->findOneByIdentifier((string)$tokenId);
        if ($token !== null) {
            $token->setRevoked(true);
            $this->doctrineTokenRepo->update($token);
        }
    }

    public function isAccessTokenRevoked($tokenId): bool
    {
        $token = $this->doctrineTokenRepo->findOneByIdentifier((string)$tokenId);
        return $token === null || $token->isRevoked();
    }

    public function findByIdentifier(string $tokenId): ?AccessToken
    {
        return $this->doctrineTokenRepo->findOneByIdentifier($tokenId);
    }
}
