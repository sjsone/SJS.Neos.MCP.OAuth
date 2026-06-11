<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Controller\Backend;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Fusion\View\FusionView;
use Neos\Party\Domain\Model\AbstractParty;
use Neos\Party\Domain\Repository\PartyRepository;
use SJS\Neos\MCP\Domain\Repository\ConnectionDataRepository;
use SJS\Neos\MCP\OAuth\Domain\Model\Client;
use SJS\Neos\MCP\OAuth\Domain\Repository\AccessTokenRepository;
use SJS\Neos\MCP\OAuth\Domain\Repository\AuthCodeRepository;
use SJS\Neos\MCP\OAuth\Domain\Repository\ClientRepository;
use SJS\Neos\MCP\OAuth\Domain\Repository\RefreshTokenRepository;
use Neos\Flow\Configuration\ConfigurationManager;

class OAuthClientController extends ActionController
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
    protected PartyRepository $partyRepository;

    #[Flow\Inject]
    protected PersistenceManagerInterface $persistenceManager;

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
        return "resource://SJS.Neos.MCP.OAuth/Private/Fusion/Module/OAuthClient/Root.fusion";
    }

    protected function authorizeClient(Client $client): void
    {
        // Admin: no client-level restriction.
    }

    protected function resolveCreateParty(?AbstractParty $party): ?AbstractParty
    {
        return $party;
    }

    protected function redirectAfterMutation(?AbstractParty $party): void
    {
        if ($party !== null) {
            $this->redirect('party', null, null, ['party' => $party]);
        } else {
            $this->redirect('index');
        }
    }

    // —— Admin-only actions ———————————————————————————————————————————

    public function indexAction(): void
    {
        $partyMap = [];
        $seenPartyIds = [];
        $unassociatedClients = [];

        $clients = $this->clientRepository->findAllClients();
        foreach ($clients as $client) {
            $party = $client->getParty();
            if ($party === null) {
                $unassociatedClients[] = $client;
                continue;
            }

            $partyId = $this->persistenceManager->getIdentifierByObject($party);
            if (\in_array($partyId, $seenPartyIds, true)) {
                continue;
            }
            $seenPartyIds[] = $partyId;

            $partyClients = $this->clientRepository->findByParty($party);
            $clientCount = 0;
            $activeTokenCount = 0;
            $totalTokenCount = 0;

            foreach ($partyClients as $partyClient) {
                $clientCount++;
                $data = $this->buildClientData($partyClient);
                $activeTokenCount += $data['activeTokenCount'];
                $totalTokenCount += $data['totalTokenCount'];
            }

            $partyMap[] = [
                'party' => $party,
                'clientCount' => $clientCount,
                'activeTokenCount' => $activeTokenCount,
                'totalTokenCount' => $totalTokenCount,
            ];
        }

        if (\count($unassociatedClients) > 0) {
            $activeTokenCount = 0;
            $totalTokenCount = 0;
            foreach ($unassociatedClients as $client) {
                $data = $this->buildClientData($client);
                $activeTokenCount += $data['activeTokenCount'];
                $totalTokenCount += $data['totalTokenCount'];
            }
            $partyMap[] = [
                'party' => null,
                'clientCount' => \count($unassociatedClients),
                'activeTokenCount' => $activeTokenCount,
                'totalTokenCount' => $totalTokenCount,
            ];
        }

        $this->view->assign('parties', $partyMap);
    }

    public function partyAction(AbstractParty $party): void
    {
        $clients = $this->clientRepository->findByParty($party);
        $clientData = [];
        foreach ($clients as $client) {
            $clientData[] = $this->buildClientData($client);
        }

        $this->view->assign('party', $party);
        $this->view->assign('clients', $clientData);
    }
}
