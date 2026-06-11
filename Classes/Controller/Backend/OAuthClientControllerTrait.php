<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Controller\Backend;

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Fusion\View\FusionView;
use Neos\Party\Domain\Model\AbstractParty;
use SJS\Neos\MCP\Domain\Repository\ConnectionDataRepository;
use SJS\Neos\MCP\OAuth\Domain\Model\AccessToken;
use SJS\Neos\MCP\OAuth\Domain\Model\Client;
use SJS\Neos\MCP\OAuth\Domain\Repository\AccessTokenRepository;
use SJS\Neos\MCP\OAuth\Domain\Repository\AuthCodeRepository;
use SJS\Neos\MCP\OAuth\Domain\Repository\ClientRepository;
use SJS\Neos\MCP\OAuth\Domain\Repository\RefreshTokenRepository;

/**
 * Shared OAuth client CRUD actions for both admin and user controllers.
 *
 * The host class must inject: ClientRepository, AccessTokenRepository,
 * RefreshTokenRepository, AuthCodeRepository, ConnectionDataRepository,
 * ConfigurationManager; and implement authorizeClient(), resolveCreateParty(),
 * redirectAfterMutation(), getFusionPathPattern().
 */
trait OAuthClientControllerTrait
{
    /** @var array<string,string>|null */
    protected ?array $availableScopes = null;

    private function initializeOAuthClientController(): void
    {
        $settings = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'SJS.Flow.MCP'
        );
        $this->availableScopes = $settings['server']['mcp']['oauth']['scopes'] ?? [];
    }

    private function initializeOAuthClientView(\Neos\Flow\Mvc\View\ViewInterface $view): void
    {
        if ($view instanceof FusionView) {
            $view->setFusionPathPattern($this->getFusionPathPattern());
        }
    }

    abstract protected function getFusionPathPattern(): string;
    abstract protected function authorizeClient(Client $client): void;
    abstract protected function resolveCreateParty(?AbstractParty $party): ?AbstractParty;
    abstract protected function redirectAfterMutation(?AbstractParty $party): void;

    // —— Shared actions ——————————————————————————————————————————————

    public function newAction(?AbstractParty $party = null): void
    {
        $this->view->assign('availableScopes', $this->availableScopes ?? []);
        $this->view->assign('party', $party);
    }

    public function initializeCreateAction(): void
    {
        $this->arguments['redirectUris']->getPropertyMappingConfiguration()->allowAllProperties();
        $this->arguments['grants']->getPropertyMappingConfiguration()->allowAllProperties();
        $this->arguments['scopes']->getPropertyMappingConfiguration()->allowAllProperties();
    }

    public function createAction(
        string $name,
        ?AbstractParty $party = null,
        array $redirectUris = [],
        array $grants = [],
        array $scopes = []
    ): void {
        $party = $this->resolveCreateParty($party);
        if ($party === null) {
            $this->addFlashMessage('Could not determine the user.', 'Error', \Neos\Error\Messages\Message::SEVERITY_ERROR);
            $this->redirect('index');
        }

        $client = new Client();
        $client->setParty($party);
        $client->setName($name);
        $client->setRedirectUris($redirectUris ?? []);
        if ($this->request->hasArgument('grants')) {
            $client->setGrants($grants);
        }
        if ($this->request->hasArgument('scopes')) {
            $client->setScopes($scopes);
        }

        $rawSecret = Client::generateClientSecret();
        $client->setClientSecret($rawSecret);

        $this->clientRepository->add($client);

        $this->addFlashMessage(
            \sprintf('Client "%s" created. Client ID: %s, Client Secret (shown once): %s', $name, $client->getClientId(), $rawSecret),
            'Client Created',
            \Neos\Error\Messages\Message::SEVERITY_OK
        );

        $this->redirectAfterMutation($party);
    }

    public function editAction(Client $client): void
    {
        $this->authorizeClient($client);

        $this->view->assign('client', $client);
        $this->view->assign('availableScopes', $this->availableScopes ?? []);
        $this->view->assign('checkedGrants', \array_flip($client->getGrants()));
        $this->view->assign('checkedScopes', \array_flip($client->getScopes()));
    }

    public function initializeUpdateAction(): void
    {
        $this->arguments['redirectUris']->getPropertyMappingConfiguration()->allowAllProperties();
        $this->arguments['grants']->getPropertyMappingConfiguration()->allowAllProperties();
        $this->arguments['scopes']->getPropertyMappingConfiguration()->allowAllProperties();
    }

    public function updateAction(
        Client $client,
        string $name,
        array $redirectUris = [],
        array $grants = [],
        array $scopes = []
    ): void {
        $this->authorizeClient($client);

        $client->setName($name);
        $client->setRedirectUris($redirectUris ?? []);
        if ($this->request->hasArgument('grants')) {
            $client->setGrants($grants);
        }
        if ($this->request->hasArgument('scopes')) {
            $client->setScopes($scopes);
        }

        $this->clientRepository->update($client);

        $this->addFlashMessage(
            \sprintf('Client "%s" updated.', $name),
            'Client Updated',
            \Neos\Error\Messages\Message::SEVERITY_OK
        );

        $this->redirectAfterMutation($client->getParty());
    }

    public function deleteAction(Client $client): void
    {
        $this->authorizeClient($client);

        $party = $client->getParty();
        $name = $client->getName();

        foreach ($this->authCodeRepository->findAll() as $authCode) {
            if ($authCode->getClient() === $client) {
                $this->authCodeRepository->remove($authCode);
            }
        }

        foreach ($this->accessTokenRepository->findByClient($client) as $token) {
            foreach ($this->refreshTokenRepository->findAll() as $refreshToken) {
                if ($refreshToken->getAccessToken() === $token) {
                    $this->refreshTokenRepository->remove($refreshToken);
                }
            }
            $connectionData = $this->connectionDataRepository->findOneByToken($token->getIdentifier());
            if ($connectionData !== null) {
                $this->connectionDataRepository->remove($connectionData);
            }
            $this->accessTokenRepository->remove($token);
        }

        $this->clientRepository->remove($client);

        $this->addFlashMessage(
            \sprintf('Client "%s" and all its tokens deleted.', $name),
            'Client Deleted',
            \Neos\Error\Messages\Message::SEVERITY_OK
        );

        $this->redirectAfterMutation($party);
    }

    public function regenerateSecretAction(Client $client): void
    {
        $this->authorizeClient($client);

        $rawSecret = Client::generateClientSecret();
        $client->setClientSecret($rawSecret);
        $this->clientRepository->update($client);

        $this->addFlashMessage(
            \sprintf('New secret for "%s" (shown once): %s', $client->getName(), $rawSecret),
            'Secret Regenerated',
            \Neos\Error\Messages\Message::SEVERITY_OK
        );

        $this->redirectAfterMutation($client->getParty());
    }

    public function tokensAction(Client $client): void
    {
        $this->authorizeClient($client);

        $tokens = [];
        foreach ($this->accessTokenRepository->findByClient($client) as $token) {
            $tokens[] = $token;
        }

        $this->view->assign('client', $client);
        $this->view->assign('tokens', $tokens);
    }

    public function revokeTokenAction(AccessToken $token): void
    {
        $client = $token->getClient();
        $this->authorizeClient($client);

        $this->revokeToken($token);

        $this->addFlashMessage(
            \sprintf('Token revoked for client "%s".', $client->getName()),
            'Token Revoked',
            \Neos\Error\Messages\Message::SEVERITY_OK
        );

        $this->redirect('tokens', null, null, ['client' => $client]);
    }

    public function revokeAllTokensAction(Client $client): void
    {
        $this->authorizeClient($client);

        $count = 0;
        foreach ($this->accessTokenRepository->findByClient($client) as $token) {
            if (!$token->isRevoked()) {
                $this->revokeToken($token);
                $count++;
            }
        }

        $this->addFlashMessage(
            \sprintf('%d token(s) revoked for client "%s".', $count, $client->getName()),
            'Tokens Revoked',
            \Neos\Error\Messages\Message::SEVERITY_OK
        );

        $this->redirectAfterMutation($client->getParty());
    }

    // —— Helpers ———————————————————————————————————————————————————————

    private function revokeToken(AccessToken $token): void
    {
        $token->setRevoked(true);
        $this->accessTokenRepository->update($token);

        foreach ($this->refreshTokenRepository->findAll() as $refreshToken) {
            if ($refreshToken->getAccessToken() === $token) {
                $refreshToken->setRevoked(true);
                $this->refreshTokenRepository->update($refreshToken);
            }
        }

        $connectionData = $this->connectionDataRepository->findOneByToken($token->getIdentifier());
        if ($connectionData !== null) {
            $this->connectionDataRepository->remove($connectionData);
        }
    }

    protected function buildClientData(Client $client): array
    {
        $tokens = $this->accessTokenRepository->findByClient($client);
        $activeCount = 0;
        $totalCount = 0;
        foreach ($tokens as $token) {
            $totalCount++;
            if (!$token->isRevoked() && !$token->isExpired()) {
                $activeCount++;
            }
        }
        return [
            'client' => $client,
            'activeTokenCount' => $activeCount,
            'totalTokenCount' => $totalCount,
        ];
    }
}
