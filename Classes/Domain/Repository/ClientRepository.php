<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Domain\Repository;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Repository;
use Neos\Party\Domain\Model\AbstractParty;
use SJS\Neos\MCP\OAuth\Domain\Model\Client;

/**
 * @extends Repository<Client>
 */
#[Flow\Scope('singleton')]
class ClientRepository extends Repository
{
    public function findOneByClientId(string $clientId): ?Client
    {
        $query = $this->createQuery();
        $query->matching($query->equals('clientId', $clientId));
        return $query->execute()->getFirst();
    }

    /**
     * @return \Neos\Flow\Persistence\QueryResultInterface<Client>
     */
    public function findByParty(AbstractParty $party): \Neos\Flow\Persistence\QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching($query->equals('party', $party));
        return $query->execute();
    }

    /**
     * @return \Neos\Flow\Persistence\QueryResultInterface<Client>
     */
    public function findAllClients(): \Neos\Flow\Persistence\QueryResultInterface
    {
        return $this->createQuery()->execute();
    }
}
