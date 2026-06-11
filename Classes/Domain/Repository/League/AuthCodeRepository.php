<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Domain\Repository\League;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Neos\Flow\Annotations as Flow;
use SJS\Neos\MCP\OAuth\Domain\Model\AuthCode;
use SJS\Neos\MCP\OAuth\Domain\Repository\AuthCodeRepository as DoctrineAuthCodeRepository;
use SJS\Neos\MCP\OAuth\Domain\ValueObject\LeagueAuthCode;
use SJS\Neos\MCP\OAuth\Domain\ValueObject\LeagueScope;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    #[Flow\Inject]
    protected DoctrineAuthCodeRepository $doctrineAuthCodeRepo;

    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new LeagueAuthCode();
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $client = $authCodeEntity->getClient();
        if (!\method_exists($client, 'getWrappedClient')) {
            return;
        }

        $doctrineEntity = new AuthCode();
        $doctrineEntity->setClient($client->getWrappedClient());
        $doctrineEntity->setIdentifier($authCodeEntity->getIdentifier());
        $doctrineEntity->setExpiryDateTime(\DateTime::createFromImmutable($authCodeEntity->getExpiryDateTime()));
        $doctrineEntity->setUserIdentifier($authCodeEntity->getUserIdentifier() ? (string)$authCodeEntity->getUserIdentifier() : null);
        $scopes = \array_map(fn(LeagueScope $s) => $s->getIdentifier(), $authCodeEntity->getScopes());
        $doctrineEntity->setScopes($scopes);
        $doctrineEntity->setRedirectUri($authCodeEntity->getRedirectUri() ?? '');
        $this->doctrineAuthCodeRepo->add($doctrineEntity);
    }

    public function revokeAuthCode($codeId): void
    {
        $code = $this->doctrineAuthCodeRepo->findOneByIdentifier((string)$codeId);
        if ($code !== null) {
            $code->setRevoked(true);
            $this->doctrineAuthCodeRepo->update($code);
        }
    }

    public function isAuthCodeRevoked($codeId): bool
    {
        $code = $this->doctrineAuthCodeRepo->findOneByIdentifier((string)$codeId);
        return $code === null || $code->isRevoked();
    }
}
