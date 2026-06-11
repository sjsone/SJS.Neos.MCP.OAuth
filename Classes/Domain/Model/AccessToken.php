<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Domain\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Entity
 */
class AccessToken
{
    /**
     * @ORM\ManyToOne
     * @var Client
     */
    protected Client $client;

    /**
     * @ORM\Column(length=255)
     * @var string
     */
    protected string $identifier = '';

    /**
     * @ORM\Column(type="datetime")
     * @var DateTime
     */
    protected DateTime $expiryDateTime;

    /**
     * @ORM\Column(length=255, nullable=true)
     * @var ?string
     */
    protected ?string $userIdentifier = null;

    /**
     * @ORM\Column(type="simple_array")
     * @var array<string>
     */
    protected array $scopes = [];

    /**
     * @ORM\Column(type="boolean")
     * @var bool
     */
    protected bool $revoked = false;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var ?DateTime
     */
    protected ?DateTime $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function setClient(Client $client): self
    {
        $this->client = $client;
        return $this;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;
        return $this;
    }

    public function getExpiryDateTime(): DateTime
    {
        return $this->expiryDateTime;
    }

    public function setExpiryDateTime(DateTime $expiryDateTime): self
    {
        $this->expiryDateTime = $expiryDateTime;
        return $this;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(?string $userIdentifier): self
    {
        $this->userIdentifier = $userIdentifier;
        return $this;
    }

    /**
     * @return array<string>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * @param array<string> $scopes
     */
    public function setScopes(array $scopes): self
    {
        $this->scopes = $scopes;
        return $this;
    }

    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    public function setRevoked(bool $revoked): self
    {
        $this->revoked = $revoked;
        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function isExpired(): bool
    {
        return $this->expiryDateTime < new DateTime();
    }
}
