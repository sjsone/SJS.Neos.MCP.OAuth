<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Domain\ValueObject;

use DateTimeImmutable;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

class LeagueAuthCode implements AuthCodeEntityInterface
{
    private string $identifier;
    private ClientEntityInterface $client;
    private string|int|null $userIdentifier = null;
    /** @var ScopeEntityInterface[] */
    private array $scopes = [];
    private DateTimeImmutable $expiryDateTime;
    private ?string $redirectUri = null;

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

    public function addScope(ScopeEntityInterface $scope): void
    {
        $this->scopes[] = $scope;
    }

    /**
     * @param ScopeEntityInterface[] $scopes
     */
    public function setScopes(array $scopes): void
    {
        $this->scopes = $scopes;
    }

    public function getExpiryDateTime(): DateTimeImmutable
    {
        return $this->expiryDateTime;
    }

    public function setExpiryDateTime(DateTimeImmutable $dateTime): void
    {
        $this->expiryDateTime = $dateTime;
    }

    public function getRedirectUri(): ?string
    {
        return $this->redirectUri;
    }

    public function setRedirectUri($uri): void
    {
        $this->redirectUri = (string)$uri;
    }
}
