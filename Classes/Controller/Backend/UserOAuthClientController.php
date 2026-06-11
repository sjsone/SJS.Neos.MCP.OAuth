<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Controller\Backend;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Domain\Service\UserService;
use Neos\Party\Domain\Model\AbstractParty;
use SJS\Neos\MCP\Domain\Repository\ConnectionDataRepository;
use SJS\Neos\MCP\OAuth\Domain\Model\Client;
use SJS\Neos\MCP\OAuth\Domain\Repository\AccessTokenRepository;
use SJS\Neos\MCP\OAuth\Domain\Repository\AuthCodeRepository;
use SJS\Neos\MCP\OAuth\Domain\Repository\ClientRepository;
use SJS\Neos\MCP\OAuth\Domain\Repository\RefreshTokenRepository;

class UserOAuthClientController extends ActionController
{
    use OAuthClientControllerTrait;

    protected $defaultViewObjectName = FusionView::class;

    #[Flow\Inject]
    protected ClientRepository $clientRepository;

    #[Flow\Inject]
    protected AccessTokenRepository $accessTokenRepository;

    #[Flow\Inject]
    protected RefreshTokenRepository $refreshTokenRepository;

    #[Flow\Inject]
    protected AuthCodeRepository $authCodeRepository;

    #[Flow\Inject]
    protected ConnectionDataRepository $connectionDataRepository;

    #[Flow\Inject]
    protected ConfigurationManager $configurationManager;

    #[Flow\Inject]
    protected UserService $userService;

    public function initializeObject(): void
    {
        $this->initializeOAuthClientController();
    }

    public function initializeView(\Neos\Flow\Mvc\View\ViewInterface $view): void
    {
        $this->initializeOAuthClientView($view);
    }

    protected function getFusionPathPattern(): string
    {
        return "resource://SJS.Neos.MCP.OAuth/Private/Fusion/Module/UserOAuthClient/Root.fusion";
    }

    protected function authorizeClient(Client $client): void
    {
        $currentUser = $this->userService->getCurrentUser();
        $clientParty = $client->getParty();

        if ($clientParty === null || $currentUser === null || $clientParty !== $currentUser) {
            $this->addFlashMessage(
                'You can only manage your own OAuth clients.',
                'Access Denied',
                \Neos\Error\Messages\Message::SEVERITY_ERROR
            );
            $this->redirect('index');
        }
    }

    protected function resolveCreateParty(?AbstractParty $party): ?AbstractParty
    {
        return $this->userService->getCurrentUser();
    }

    protected function redirectAfterMutation(?AbstractParty $party): void
    {
        $this->redirect('index');
    }

    // —— User-only actions ————————————————————————————————————————————

    public function indexAction(): void
    {
        $currentUser = $this->userService->getCurrentUser();
        if ($currentUser === null) {
            $this->view->assign('clients', []);
            return;
        }

        $clients = $this->clientRepository->findByParty($currentUser);
        $clientData = [];
        foreach ($clients as $client) {
            $clientData[] = $this->buildClientData($client);
        }

        $this->view->assign('clients', $clientData);
    }
}
