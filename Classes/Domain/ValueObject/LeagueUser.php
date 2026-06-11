<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Domain\ValueObject;

use League\OAuth2\Server\Entities\UserEntityInterface;

class LeagueUser implements UserEntityInterface
{
    private string $identifier;

    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
