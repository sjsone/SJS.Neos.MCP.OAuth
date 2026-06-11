<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Domain\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Neos\Flow\Annotations as Flow;
use Neos\Party\Domain\Model\AbstractParty;

/**
 * @Flow\Entity
 */
class Client
{
    /**
     * @ORM\ManyToOne
     * @var AbstractParty
     */
    protected ?AbstractParty $party = null;

    /**
     * @ORM\Column(length=255)
     * @var string
     */
    protected string $name = '';

    /**
     * @ORM\Column(length=255, unique=true)
     * @var string
     */
    protected string $clientId = '';

    /**
     * @ORM\Column(length=255)
     * @var string
     */
    protected string $clientSecretHash = '';

    /**
     * @ORM\Column(type="simple_array")
     * @var array<string>
     */
    protected array $redirectUris = [];

    /**
     * @ORM\Column(type="simple_array")
     * @var array<string>
     */
    protected array $grants = ['authorization_code', 'refresh_token'];

    /**
     * @ORM\Column(type="simple_array")
     * @var array<string>
     */
    protected array $scopes = [];

    /**
     * @ORM\Column(type="datetime")
     * @var DateTime
     */
    protected DateTime $createdAt;

    /**
     * @ORM\Column(type="boolean")
     * @var bool
     */
    protected bool $enabled = true;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->clientId = self::generateClientId();
    }

    public static function generateClientId(): string
    {
        return \bin2hex(\random_bytes(16));
    }

    public static function generateClientSecret(): string
    {
        return \bin2hex(\random_bytes(32));
    }

    public function verifyClientSecret(string $secret): bool
    {
        return password_verify($secret, $this->clientSecretHash);
    }

    public function setClientSecret(string $plainSecret): void
    {
        $this->clientSecretHash = password_hash($plainSecret, PASSWORD_BCRYPT);
    }

    public function getParty(): ?AbstractParty
    {
        return $this->party;
    }

    public function setParty(?AbstractParty $party): self
    {
        $this->party = $party;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): self
    {
        $this->clientId = $clientId;
        return $this;
    }

    public function getClientSecretHash(): string
    {
        return $this->clientSecretHash;
    }

    /**
     * @return array<string>
     */
    public function getRedirectUris(): array
    {
        return $this->redirectUris;
    }

    /**
     * @param array<string> $redirectUris
     */
    public function setRedirectUris(array $redirectUris): self
    {
        $this->redirectUris = $redirectUris;
        return $this;
    }

    /**
     * @return array<string>
     */
    public function getGrants(): array
    {
        return $this->grants;
    }

    /**
     * @param array<string> $grants
     */
    public function setGrants(array $grants): self
    {
        $this->grants = $grants;
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

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }
}
