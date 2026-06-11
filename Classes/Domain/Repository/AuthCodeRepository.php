<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Domain\Repository;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Repository;
use SJS\Neos\MCP\OAuth\Domain\Model\AuthCode;

/**
 * @extends Repository<AuthCode>
 */
#[Flow\Scope('singleton')]
class AuthCodeRepository extends Repository
{
    public function findOneByIdentifier(string $identifier): ?AuthCode
    {
        $query = $this->createQuery();
        $query->matching($query->equals('identifier', $identifier));
        return $query->execute()->getFirst();
    }
}
