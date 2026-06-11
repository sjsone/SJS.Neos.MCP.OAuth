<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Command;

use Neos\Flow\Cli\CommandController;
use Neos\Flow\Annotations as Flow;

class OAuthCommandController extends CommandController
{
    /**
     * Generate an encryption key for OAuth2 authorization codes.
     *
     * This outputs a hex-encoded 32-byte key. Add it to your
     * Settings.SJS.Flow.MCP.yaml under the oauth section.
     */
    public function generateEncryptionKeyCommand(): void
    {
        $key = \bin2hex(\random_bytes(32));
        $this->outputLine('Set the following environment variable:');
        $this->outputLine('<comment>SJS_FLOW_MCP_OAUTH_ENCRYPTION_KEY=%s</comment>', [$key]);
        $this->outputLine();
        $this->outputLine('Or add it to your Settings.SJS.Flow.MCP.yaml:');
        $this->outputLine('<comment>SJS.Flow.MCP.server.mcp.oauth.encryptionKey: \'%s\'</comment>', [$key]);
    }
}
