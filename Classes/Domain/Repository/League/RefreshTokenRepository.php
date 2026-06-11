<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Domain\Repository\League;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Neos\Flow\Annotations as Flow;
use SJS\Neos\MCP\OAuth\Domain\Model\RefreshToken;
use SJS\Neos\MCP\OAuth\Domain\Repository\AccessTokenRepository as DoctrineAccessTokenRepository;
use SJS\Neos\MCP\OAuth\Domain\Repository\RefreshTokenRepository as DoctrineRefreshTokenRepository;
use SJS\Neos\MCP\OAuth\Domain\ValueObject\LeagueRefreshToken;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    #[Flow\Inject]
    protected DoctrineRefreshTokenRepository $doctrineRefreshTokenRepo;

    #[Flow\Inject]
    protected DoctrineAccessTokenRepository $doctrineAccessTokenRepo;

    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new LeagueRefreshToken();
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $accessTokenId = $refreshTokenEntity->getAccessToken()->getIdentifier();
        $doctrineAccessToken = $this->doctrineAccessTokenRepo->findOneByIdentifier($accessTokenId);

        if ($doctrineAccessToken === null) {
            return;
        }

        $doctrineEntity = new RefreshToken();
        $doctrineEntity->setAccessToken($doctrineAccessToken);
        $doctrineEntity->setIdentifier($refreshTokenEntity->getIdentifier());
        $doctrineEntity->setExpiryDateTime(\DateTime::createFromImmutable($refreshTokenEntity->getExpiryDateTime()));
        $this->doctrineRefreshTokenRepo->add($doctrineEntity);
    }

    public function revokeRefreshToken($tokenId): void
    {
        $token = $this->doctrineRefreshTokenRepo->findOneByIdentifier((string)$tokenId);
        if ($token !== null) {
            $token->setRevoked(true);
            $this->doctrineRefreshTokenRepo->update($token);
        }
    }

    public function isRefreshTokenRevoked($tokenId): bool
    {
        $token = $this->doctrineRefreshTokenRepo->findOneByIdentifier((string)$tokenId);
        return $token === null || $token->isRevoked();
    }
}
