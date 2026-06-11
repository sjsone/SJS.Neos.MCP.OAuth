<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Service;

use DateInterval;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use SJS\Neos\MCP\OAuth\Domain\Repository\League\AccessTokenRepository;
use SJS\Neos\MCP\OAuth\Domain\Repository\League\AuthCodeRepository;
use SJS\Neos\MCP\OAuth\Domain\Repository\League\ClientRepository;
use SJS\Neos\MCP\OAuth\Domain\Repository\League\RefreshTokenRepository;
use SJS\Neos\MCP\OAuth\Domain\Repository\League\ScopeRepository;
use SJS\Neos\MCP\OAuth\Service\OpaqueTokenValidator;

#[Flow\Scope('singleton')]
class OAuthServerService
{
    #[Flow\Inject]
    protected ClientRepository $clientRepository;

    #[Flow\Inject]
    protected AccessTokenRepository $accessTokenRepository;

    #[Flow\Inject]
    protected AuthCodeRepository $authCodeRepository;

    #[Flow\Inject]
    protected RefreshTokenRepository $refreshTokenRepository;

    #[Flow\Inject]
    protected ConfigurationManager $configurationManager;

    /** @var array<string,AuthorizationServer> */
    private array $authorizationServers = [];

    /** @var array<string,ResourceServer> */
    private array $resourceServers = [];

    /**
     * Resolve the OAuth settings for a specific MCP server name.
     *
     * @return array<string,mixed>
     */
    public function getSettingsForServer(string $serverName): array
    {
        $settings = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'SJS.Flow.MCP'
        );
        return $settings['server'][$serverName]['oauth'] ?? [];
    }

    /**
     * Create a ScopeRepository with scope definitions for a specific server.
     */
    protected function buildScopeRepository(string $serverName): ScopeRepository
    {
        $settings = $this->getSettingsForServer($serverName);
        $scopes = $settings['scopes'] ?? [];

        $scopeRepository = new ScopeRepository();
        $scopeRepository->setScopeDefinitions($scopes);
        return $scopeRepository;
    }

    public function getAuthorizationServer(string $serverName = 'mcp'): AuthorizationServer
    {
        if (!isset($this->authorizationServers[$serverName])) {
            $settings = $this->getSettingsForServer($serverName);

            $encryptionKey = $settings['encryptionKey'] ?? null;

            if ($encryptionKey === null || $encryptionKey === '') {
                throw new \RuntimeException(
                    'No encryption key configured for the OAuth2 authorization server. '
                    . 'Generate one with: ./flow oauth:generateEncryptionKey '
                    . 'Then set it in Settings.SJS.Flow.MCP.yaml under '
                    . 'SJS.Flow.MCP.server.mcp.oauth.encryptionKey'
                );
            }

            $keyBytes = \hex2bin($encryptionKey);
            if ($keyBytes === false) {
                throw new \RuntimeException(
                    'The encryption key must be a hex-encoded string. '
                    . 'Generate one with: ./flow oauth:generateEncryptionKey'
                );
            }

            if (\strlen($keyBytes) < 32) {
                throw new \RuntimeException(
                    \sprintf(
                        'The encryption key must be at least 32 bytes (256 bits), got %d bytes (%d hex chars). '
                        . 'Generate a proper key with: ./flow oauth:generateEncryptionKey',
                        \strlen($keyBytes),
                        \strlen($encryptionKey)
                    )
                );
            }

            // Generate an ephemeral RSA key pair for the League library's internal use.
            // The actual token format is opaque (not JWT) because
            // LeagueAccessToken::__toString() returns the token identifier,
            // bypassing the JWT signing the trait would otherwise perform.
            $privateKey = $this->createEphemeralPrivateKey();

            $server = new AuthorizationServer(
                $this->clientRepository,
                $this->accessTokenRepository,
                $this->buildScopeRepository($serverName),
                $privateKey,
                $encryptionKey
            );

            $accessTokenTTL = new DateInterval($settings['accessTokenTTL'] ?? 'PT1H');
            $refreshTokenTTL = new DateInterval($settings['refreshTokenTTL'] ?? 'P30D');
            $authCodeTTL = new DateInterval($settings['authCodeTTL'] ?? 'PT10M');

            $authCodeGrant = new AuthCodeGrant(
                $this->authCodeRepository,
                $this->refreshTokenRepository,
                $authCodeTTL
            );
            $authCodeGrant->setRefreshTokenTTL($refreshTokenTTL);

            $server->enableGrantType($authCodeGrant, $accessTokenTTL);

            $refreshTokenGrant = new RefreshTokenGrant($this->refreshTokenRepository);
            $refreshTokenGrant->setRefreshTokenTTL($refreshTokenTTL);

            $server->enableGrantType($refreshTokenGrant, $accessTokenTTL);

            $server->enableGrantType(new ClientCredentialsGrant(), $accessTokenTTL);

            $this->authorizationServers[$serverName] = $server;
        }

        return $this->authorizationServers[$serverName];
    }

    public function getResourceServer(string $serverName = 'mcp'): ResourceServer
    {
        if (!isset($this->resourceServers[$serverName])) {
            // Generate an ephemeral public key for the ResourceServer constructor.
            // The actual validation is performed by OpaqueTokenValidator, which
            // looks up opaque tokens in the repository instead of verifying JWT
            // signatures. The public key is stored but never used because the
            // custom validator is not a BearerTokenValidator.
            $keyPair = $this->generateEphemeralKeyPair();

            $this->resourceServers[$serverName] = new ResourceServer(
                $this->accessTokenRepository,
                new CryptKey($keyPair['public']),
                new OpaqueTokenValidator($this->accessTokenRepository)
            );
        }

        return $this->resourceServers[$serverName];
    }

    /**
     * Generate an ephemeral RSA private key for the AuthorizationServer.
     *
     * The AuthorizationServer constructor requires a CryptKey, but with opaque
     * tokens the private key is never used for JWT signing (LeagueAccessToken::__toString()
     * returns the token identifier instead). An ephemeral key satisfies the
     * constructor requirement without needing file-based RSA key management.
     *
     * @return CryptKey
     */
    private function createEphemeralPrivateKey(): CryptKey
    {
        $keyPair = $this->generateEphemeralKeyPair();
        return new CryptKey($keyPair['private']);
    }

    /**
     * Generate an ephemeral RSA-2048 key pair, returning the PEM-encoded
     * private and public keys.
     *
     * @return array{private: string, public: string}
     */
    private function generateEphemeralKeyPair(): array
    {
        $keyResource = \openssl_pkey_new([
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        \openssl_pkey_export($keyResource, $privateKeyContents);
        $keyDetails = \openssl_pkey_get_details($keyResource);

        return [
            'private' => $privateKeyContents,
            'public' => $keyDetails['key'],
        ];
    }

    /**
     * Detect the server name from the current HTTP request.
     *
     * Checks query parameters, then the request URI path, falling back to 'mcp'.
     */
    public function detectServerNameFromRequest(\Psr\Http\Message\ServerRequestInterface $psrRequest): string
    {
        // Check query parameter first
        $params = $psrRequest->getQueryParams();
        if (!empty($params['server'])) {
            return $params['server'];
        }

        // Check the request URI for pattern like /oauth/{serverName}/...
        $path = $psrRequest->getUri()->getPath();
        if (\preg_match('#/oauth/([^/]+)/#', $path, $matches)) {
            return $matches[1];
        }

        // Default to 'mcp'
        return 'mcp';
    }
}
