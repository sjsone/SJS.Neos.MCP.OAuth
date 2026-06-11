<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Aspect;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\Session\SessionInterface;

/**
 * Intercepts the after-login redirect to complete OAuth authorization
 * flows that were suspended while the user was not authenticated.
 *
 * When OAuthController::authorizeAction() encounters an unauthenticated
 * user, it stores the full OAuth authorize URI in the session and redirects
 * to the Neos login page. After successful login, the BackendController
 * calls BackendRedirectionService::getAfterLoginRedirectionUri() to decide
 * where to send the user — this aspect checks for a stored OAuth URI and
 * returns it instead of the normal backend start module.
 */
#[Flow\Aspect]
#[Flow\Scope('singleton')]
class LoginRedirectAspect
{
    #[Flow\Inject]
    protected SessionInterface $session;

    /**
     * Around advice: if the session contains an OAuth authorize redirect URI
     * (placed there by OAuthController::authorizeAction()), consume it and
     * return it. Otherwise proceed with the normal chain.
     */
    #[Flow\Around("method(Neos\Neos\Service\BackendRedirectionService->getAfterLoginRedirectionUri())")]
    public function interceptOAuthRedirect(JoinPointInterface $joinPoint): ?string
    {
        $redirectUri = $this->session->getData('SJS_Neos_MCP_OAuth_redirectUri');

        if ($redirectUri !== null) {
            $this->session->putData('SJS_Neos_MCP_OAuth_redirectUri', null);
            return $redirectUri;
        }

        return $joinPoint->getAdviceChain()->proceed($joinPoint);
    }
}
