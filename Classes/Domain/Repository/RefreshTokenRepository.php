<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Domain\Repository;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Repository;
use SJS\Neos\MCP\OAuth\Domain\Model\RefreshToken;

/**
 * @extends Repository<RefreshToken>
 */
#[Flow\Scope('singleton')]
class RefreshTokenRepository extends Repository
{
    public function findOneByIdentifier(string $identifier): ?RefreshToken
    {
        $query = $this->createQuery();
        $query->matching($query->equals('identifier', $identifier));
        return $query->execute()->getFirst();
    }
}
