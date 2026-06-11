<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Domain\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Entity
 */
class RefreshToken
{
    /**
     * @ORM\ManyToOne
     * @var AccessToken
     */
    protected AccessToken $accessToken;

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
     * @ORM\Column(type="boolean")
     * @var bool
     */
    protected bool $revoked = false;

    public function getAccessToken(): AccessToken
    {
        return $this->accessToken;
    }

    public function setAccessToken(AccessToken $accessToken): self
    {
        $this->accessToken = $accessToken;
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

    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    public function setRevoked(bool $revoked): self
    {
        $this->revoked = $revoked;
        return $this;
    }
}
