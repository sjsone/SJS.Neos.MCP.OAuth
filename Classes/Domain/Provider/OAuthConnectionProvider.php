<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Domain\Provider;

use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\Exception\OAuthServerException;
use Neos\Flow\Annotations as Flow;
use SJS\Flow\MCP\Domain\Connection\Connection;
use SJS\Flow\MCP\Domain\Connection\ConnectionProviderInterface;
use SJS\Neos\MCP\Domain\Repository\ConnectionDataRepository;
use SJS\Neos\MCP\OAuth\Service\OAuthServerService;

class OAuthConnectionProvider implements ConnectionProviderInterface
{
    #[Flow\Inject]
    protected OAuthServerService $oauthServerService;

    #[Flow\Inject]
    protected ConnectionDataRepository $connectionDataRepository;

    public function getConnectionByTokenAndServerName(string $token, string $serverName): ?Connection
    {
        $request = new ServerRequest('GET', '', ['Authorization' => "Bearer {$token}"]);

        try {
            $validatedRequest = $this->oauthServerService
                ->getResourceServer($serverName)
                ->validateAuthenticatedRequest($request);
        } catch (OAuthServerException $e) {
            return null;
        }

        // Look up the ConnectionData that was created alongside the OAuth access token.
        // The ConnectionData shares the same token identifier.
        $connectionData = $this->connectionDataRepository->findOneByToken($token);
        if ($connectionData === null) {
            return null;
        }

        return $connectionData->createConnection();
    }
}
