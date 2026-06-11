<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Domain\Repository;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\QueryResultInterface;
use Neos\Flow\Persistence\Repository;
use SJS\Neos\MCP\OAuth\Domain\Model\AccessToken;
use SJS\Neos\MCP\OAuth\Domain\Model\Client;

/**
 * @extends Repository<AccessToken>
 */
#[Flow\Scope('singleton')]
class AccessTokenRepository extends Repository
{
    public function findOneByIdentifier(string $identifier): ?AccessToken
    {
        $query = $this->createQuery();
        $query->matching($query->equals('identifier', $identifier));
        return $query->execute()->getFirst();
    }

    /**
     * @return QueryResultInterface<AccessToken>
     */
    public function findByClient(Client $client): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching($query->equals('client', $client));
        return $query->execute();
    }

    /**
     * @return QueryResultInterface<AccessToken>
     */
    public function findAllTokens(): QueryResultInterface
    {
        return $this->createQuery()->execute();
    }
}
