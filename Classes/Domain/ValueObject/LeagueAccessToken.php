<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Domain\ValueObject;

use DateTimeImmutable;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;

class LeagueAccessToken implements AccessTokenEntityInterface
{
    use AccessTokenTrait {
        __toString as private jwtToString;
    }

    private string $identifier;
    private ClientEntityInterface $client;
    private string|int|null $userIdentifier = null;
    /** @var ScopeEntityInterface[] */
    private array $scopes = [];
    private DateTimeImmutable $expiryDateTime;

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier($identifier): void
    {
        $this->identifier = (string)$identifier;
    }

    public function getClient(): ClientEntityInterface
    {
        return $this->client;
    }

    public function setClient(ClientEntityInterface $client): void
    {
        $this->client = $client;
    }

    public function getUserIdentifier(): string|int|null
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier($identifier): void
    {
        $this->userIdentifier = $identifier;
    }

    /**
     * @return ScopeEntityInterface[]
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * @param ScopeEntityInterface[] $scopes
     */
    public function setScopes(array $scopes): void
    {
        $this->scopes = $scopes;
    }

    public function addScope(ScopeEntityInterface $scope): void
    {
        $this->scopes[] = $scope;
    }

    public function getExpiryDateTime(): DateTimeImmutable
    {
        return $this->expiryDateTime;
    }

    public function setExpiryDateTime(DateTimeImmutable $dateTime): void
    {
        $this->expiryDateTime = $dateTime;
    }

    /**
     * Return an opaque token string (the token identifier) instead of a JWT.
     * The parent trait's __toString() generates a signed JWT, but we need
     * opaque tokens consistent with ConnectionData.token format.
     */
    public function __toString(): string
    {
        return $this->getIdentifier();
    }
}
