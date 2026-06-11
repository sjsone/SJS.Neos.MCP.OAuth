<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Domain\Repository\League;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Neos\Flow\Annotations as Flow;
use SJS\Neos\MCP\OAuth\Domain\ValueObject\LeagueScope;

class ScopeRepository implements ScopeRepositoryInterface
{
    /** @var array<string,string> */
    protected array $scopeDefinitions = [];

    /**
     * Set scope definitions for a specific server.
     * Called by OAuthServerService::buildScopeRepository().
     *
     * @param array<string,string> $definitions
     */
    public function setScopeDefinitions(array $definitions): void
    {
        $this->scopeDefinitions = $definitions;
    }

    public function getScopeEntityByIdentifier($identifier): ?ScopeEntityInterface
    {
        if (isset($this->scopeDefinitions[$identifier])) {
            return new LeagueScope($identifier);
        }
        return null;
    }

    /**
     * @param ScopeEntityInterface[] $scopes
     * @param string $grantType
     * @param ClientEntityInterface $clientEntity
     * @param string|null $userIdentifier
     * @return ScopeEntityInterface[]
     */
    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null
    ): array {
        return $scopes;
    }
}
