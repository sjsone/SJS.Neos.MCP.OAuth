<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Service;

use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ServerRequestInterface;
use SJS\Neos\MCP\OAuth\Domain\Repository\League\AccessTokenRepository;

/**
 * Validates opaque OAuth2 access tokens by looking them up in the
 * AccessToken repository, without requiring JWT cryptographic verification.
 *
 * Used by the ResourceServer when tokens are opaque (non-JWT) random
 * strings stored in the database.
 */
class OpaqueTokenValidator implements AuthorizationValidatorInterface
{
    protected AccessTokenRepository $accessTokenRepository;

    public function __construct(AccessTokenRepository $accessTokenRepository)
    {
        $this->accessTokenRepository = $accessTokenRepository;
    }

    public function validateAuthorization(ServerRequestInterface $request): ServerRequestInterface
    {
        if ($request->hasHeader('authorization') === false) {
            throw OAuthServerException::accessDenied('Missing "Authorization" header');
        }

        $header = $request->getHeader('authorization');
        $token = \trim((string) \preg_replace('/^\s*Bearer\s/', '', $header[0]));

        if ($token === '') {
            throw OAuthServerException::accessDenied('Access token could not be parsed');
        }

        // Look up the token entity by its opaque identifier
        $tokenEntity = $this->accessTokenRepository->findByIdentifier($token);

        if ($tokenEntity === null) {
            throw OAuthServerException::accessDenied('Access token could not be verified');
        }

        if ($tokenEntity->isRevoked()) {
            throw OAuthServerException::accessDenied('Access token has been revoked');
        }

        if ($tokenEntity->isExpired()) {
            throw OAuthServerException::accessDenied('Access token has expired');
        }

        // Return the request with attributes that the rest of the
        // application expects (same keys as JWT BearerTokenValidator)
        return $request
            ->withAttribute('oauth_access_token_id', $token)
            ->withAttribute('oauth_client_id', $tokenEntity->getClient()->getClientId())
            ->withAttribute('oauth_user_id', $tokenEntity->getUserIdentifier() ?? '')
            ->withAttribute('oauth_scopes', $tokenEntity->getScopes());
    }
}
