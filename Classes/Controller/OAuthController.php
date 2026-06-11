<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Controller;

use GuzzleHttp\Psr7\Response as Psr7Response;
use League\OAuth2\Server\Exception\OAuthServerException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Party\Domain\Repository\PartyRepository;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Flow\Security\SessionDataContainer;
use Neos\Flow\Session\SessionInterface;
use SJS\Neos\MCP\OAuth\Domain\Model\Client;
use SJS\Neos\MCP\OAuth\Domain\Repository\ClientRepository;
use SJS\Neos\MCP\OAuth\Domain\ValueObject\LeagueUser;
use SJS\Neos\MCP\OAuth\Service\OAuthServerService;

class OAuthController extends ActionController
{
    #[Flow\Inject]
    protected OAuthServerService $oauthServerService;

    #[Flow\Inject]
    protected SecurityContext $securityContext;

    #[Flow\Inject]
    protected PersistenceManagerInterface $persistenceManager;

    #[Flow\Inject]
    protected PartyRepository $partyRepository;

    #[Flow\Inject]
    protected SessionInterface $session;

    #[Flow\Inject]
    protected ClientRepository $clientRepository;

    /** @var array<string> */
    protected $supportedMediaTypes = ['application/json', 'text/html'];

    #[Flow\SkipCsrfProtection]
    public function tokenAction(): string
    {
        $psrRequest = $this->request->getHttpRequest();
        $psrResponse = new Psr7Response();

        try {
            $serverName = $this->oauthServerService->detectServerNameFromRequest($psrRequest);
            $server = $this->oauthServerService->getAuthorizationServer($serverName);
            $response = $server->respondToAccessTokenRequest($psrRequest, $psrResponse);

            $this->response->setStatusCode($response->getStatusCode());
            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    $this->response->setHttpHeader($name, $value);
                }
            }
            $this->response->setHttpHeader('Cache-Control', 'no-store');
            $this->response->setHttpHeader('Pragma', 'no-cache');

            return (string) $response->getBody();
        } catch (OAuthServerException $e) {
            $response = $e->generateHttpResponse($psrResponse);
            $this->response->setStatusCode($response->getStatusCode());
            return (string) $response->getBody();
        }
    }

    #[Flow\Session(autoStart: true)]
    public function authorizeAction(): void
    {
        $psrRequest = $this->request->getHttpRequest();
        $serverName = $this->oauthServerService->detectServerNameFromRequest($psrRequest);
        $settings = $this->oauthServerService->getSettingsForServer($serverName);

        // Auto-register the client if it doesn't exist yet.
        // This allows MCP clients to skip the explicit registration step
        // when enableAutomaticClientRegistration is enabled.
        $this->autoRegisterClientIfMissing($psrRequest, $serverName, $settings);

        $server = $this->oauthServerService->getAuthorizationServer($serverName);

        try {
            $authRequest = $server->validateAuthorizationRequest($psrRequest);

            // Check session-level tokens directly rather than
            // $this->securityContext->getAccount(), because the
            // PersistedUsernamePassword token has request patterns
            // restricting it to Neos\Neos\Controller\*. This bypasses
            // that filter — if any token in the session is already
            // authenticated, we know the user is logged into the backend.
            $account = $this->getAuthenticatedAccountFromSession();

            if ($account === null) {
                // Store the full OAuth authorize URI in the session so an AOP
                // aspect can redirect back here after successful Neos login.
                $authorizeUri = $this->request->getHttpRequest()->getUri();
                $this->session->putData('SJS_Neos_MCP_OAuth_redirectUri', (string)$authorizeUri);
                $this->redirectToUri('/neos/login');
            }

            // Attach the authenticated user's party to the client if it was
            // auto-registered without one (e.g. created on-the-fly during
            // the first authorization request).
            $this->attachPartyToClient($authRequest, $account);

            $userEntity = new LeagueUser($account->getAccountIdentifier());
            $authRequest->setUser($userEntity);
            $authRequest->setAuthorizationApproved(true);

            $response = $server->completeAuthorizationRequest($authRequest, new Psr7Response());
            // The OAuth2 authorization code flow creates an AuthCode entity
            // that must be persisted. Flow normally blocks Doctrine writes
            // during GET (safe) requests — call persistAll() to allow it.
            $this->persistenceManager->persistAll();
            $this->redirectToUri((string) $response->getHeaderLine('Location'));
        } catch (OAuthServerException $e) {
            $redirectUri = $e->getRedirectUri();
            if ($redirectUri !== null) {
                $this->redirectToUri($redirectUri . '?error=' . $e->getErrorType() . '&message=' . urlencode($e->getMessage()));
            }
            $this->throwStatus(400, $e->getMessage());
        }
    }

    /**
     * RFC 7591 — OAuth 2.0 Dynamic Client Registration.
     *
     * Accepts JSON body:
     *   - redirect_uris (required): array of redirect URI strings
     *   - client_name (optional): human-readable client name
     *   - grant_types (optional): array of grant type strings
     *   - scope (optional): space-separated scope string
     */
    #[Flow\SkipCsrfProtection]
    public function registerAction(): string
    {
        $psrRequest = $this->request->getHttpRequest();
        $serverName = $this->oauthServerService->detectServerNameFromRequest($psrRequest);
        $settings = $this->oauthServerService->getSettingsForServer($serverName);

        if (!($settings['enableAutomaticClientRegistration'] ?? false)) {
            $this->response->setStatusCode(400);
            $this->response->setContentType('application/json');
            return \json_encode([
                'error' => 'invalid_request',
                'error_description' => 'Automatic client registration is not enabled on this server.',
            ]);
        }

        $body = (string) $psrRequest->getBody();
        $data = \json_decode($body, true);
        if (!\is_array($data)) {
            $this->response->setStatusCode(400);
            $this->response->setContentType('application/json');
            return \json_encode([
                'error' => 'invalid_request',
                'error_description' => 'Request body must be valid JSON.',
            ]);
        }

        $redirectUris = $data['redirect_uris'] ?? [];
        if (empty($redirectUris)) {
            $this->response->setStatusCode(400);
            $this->response->setContentType('application/json');
            return \json_encode([
                'error' => 'invalid_redirect_uri',
                'error_description' => 'At least one redirect_uri is required.',
            ]);
        }

        $clientName = (string) ($data['client_name'] ?? 'Auto-registered MCP Client');
        $grants = $data['grant_types'] ?? $settings['defaultGrants'] ?? ['authorization_code', 'refresh_token'];
        $scope = $data['scope'] ?? \implode(' ', $settings['defaultScopes'] ?? ['mcp/read', 'mcp/write']);

        $client = new Client();
        $client->setName($clientName);
        $client->setRedirectUris($redirectUris);
        $client->setGrants($grants);
        $client->setScopes(\is_array($scope) ? $scope : \explode(' ', $scope));

        $rawSecret = Client::generateClientSecret();
        $client->setClientSecret($rawSecret);

        $this->clientRepository->add($client);

        $this->response->setStatusCode(201);
        $this->response->setContentType('application/json');
        $this->response->setHttpHeader('Cache-Control', 'no-store');

        return \json_encode([
            'client_id' => $client->getClientId(),
            'client_secret' => $rawSecret,
            'client_name' => $client->getName(),
            'redirect_uris' => $client->getRedirectUris(),
            'grant_types' => $client->getGrants(),
            'scope' => \implode(' ', $client->getScopes()),
            'token_endpoint_auth_method' => 'client_secret_post',
        ]);
    }

    /**
     * RFC 8414 — OAuth 2.0 Authorization Server Metadata.
     *
     * Used by MCP clients to discover the authorization, token, and
     * registration endpoints, along with supported scopes and grant types.
     */
    public function wellKnownAction(): string
    {
        $psrRequest = $this->request->getHttpRequest();
        $serverName = $this->oauthServerService->detectServerNameFromRequest($psrRequest);
        $settings = $this->oauthServerService->getSettingsForServer($serverName);

        $baseUrl = \rtrim($this->request->getHttpRequest()->getUri()->getScheme() . '://' . $this->request->getHttpRequest()->getUri()->getHost(), '/');

        $scopesSupported = [];
        foreach (($settings['scopes'] ?? []) as $scopeId => $scopeDescription) {
            $scopesSupported[] = $scopeId;
        }

        $metadata = [
            'issuer' => $baseUrl,
            'authorization_endpoint' => $baseUrl . '/oauth/' . $serverName . '/authorize',
            'token_endpoint' => $baseUrl . '/oauth/' . $serverName . '/token',
            'scopes_supported' => $scopesSupported,
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token', 'client_credentials'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'client_secret_basic'],
            'code_challenge_methods_supported' => ['S256'],
        ];

        if ($settings['enableAutomaticClientRegistration'] ?? false) {
            $metadata['registration_endpoint'] = $baseUrl . '/oauth/' . $serverName . '/register';
        }

        $this->response->setContentType('application/json');
        $this->response->setHttpHeader('Cache-Control', 'public, max-age=3600');

        return \json_encode($metadata, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Auto-register an OAuth client on-the-fly if it doesn't exist yet.
     *
     * When enableAutomaticClientRegistration is true, MCP clients can skip
     * the explicit POST /oauth/mcp/register step. If a client_id in the
     * authorization request is unknown and there is no existing client with
     * that ID, a new Client is created from the request parameters (redirect_uri,
     * scope) so that validateAuthorizationRequest() succeeds.
     */
    private function autoRegisterClientIfMissing(
        \Psr\Http\Message\ServerRequestInterface $psrRequest,
        string $serverName,
        array $settings
    ): void {
        if (!($settings['enableAutomaticClientRegistration'] ?? false)) {
            return;
        }

        $params = $psrRequest->getQueryParams();
        $clientId = $params['client_id'] ?? null;
        if ($clientId === null || $clientId === '') {
            return;
        }

        // Already exists — nothing to do
        $existing = $this->clientRepository->findOneByClientId($clientId);
        if ($existing !== null) {
            return;
        }

        $redirectUri = $params['redirect_uri'] ?? '';
        $scope = $params['scope'] ?? '';

        // Derive a human-readable name from the redirect_uri host,
        // e.g. "MCP Client (vscode.local)" or "MCP Client (127.0.0.1)"
        $host = $redirectUri !== '' ? parse_url($redirectUri, PHP_URL_HOST) : null;
        $clientName = $host !== null ? "MCP Client ({$host})" : 'Auto-registered MCP Client';

        $client = new Client();
        $client->setClientId($clientId);
        $client->setName($clientName);
        $client->setRedirectUris($redirectUri !== '' ? [$redirectUri] : []);
        $client->setGrants($settings['defaultGrants'] ?? ['authorization_code', 'refresh_token']);
        $client->setScopes($scope !== '' ? \explode(' ', $scope) : ($settings['defaultScopes'] ?? ['mcp/read', 'mcp/write']));

        // Do NOT set a client secret — the MCP client provided its own
        // client_id and won't know our randomly generated secret.
        // PKCE code_challenge provides the security for the authorization
        // code flow. An empty secret hash means the token endpoint skips
        // client_secret validation (see ClientRepository::validateClient()).

        $this->clientRepository->add($client);
        $this->persistenceManager->persistAll();
    }

    /**
     * Attach the authenticated user's party to an auto-registered client
     * that was created without a party association.
     */
    private function attachPartyToClient(
        \League\OAuth2\Server\RequestTypes\AuthorizationRequest $authRequest,
        \Neos\Flow\Security\Account $account
    ): void {
        $clientEntity = $authRequest->getClient();
        if (!\method_exists($clientEntity, 'getWrappedClient')) {
            return;
        }

        $client = $clientEntity->getWrappedClient();
        if ($client->getParty() !== null) {
            return; // Already has a party
        }

        $party = $this->partyRepository->findOneHavingAccount($account);
        if ($party === null) {
            return;
        }

        $client->setParty($party);
        $this->clientRepository->update($client);
    }

    /**
     * Checks the session-level security tokens for an authenticated account,
     * bypassing the request-pattern filtering that SecurityContext::getAccount()
     * applies. This is needed because the Neos.Neos:Backend
     * PersistedUsernamePassword token only matches Neos\Neos\Controller\*
     * request patterns, and our OAuth controller lives in a different namespace.
     *
     * @return \Neos\Flow\Security\Account|null
     */
    private function getAuthenticatedAccountFromSession(): ?\Neos\Flow\Security\Account
    {
        $sessionDataContainer = $this->objectManager->get(SessionDataContainer::class);
        foreach ($sessionDataContainer->getSecurityTokens() as $token) {
            if ($token->isAuthenticated()) {
                return $token->getAccount();
            }
        }
        return null;
    }
}
