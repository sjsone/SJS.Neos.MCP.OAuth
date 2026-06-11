<?php

declare(strict_types=1);

namespace SJS\Neos\MCP\OAuth\Command;

use Neos\Flow\Cli\CommandController;
use Neos\Flow\Annotations as Flow;

class OAuthCommandController extends CommandController
{
    /**
     * Generate RSA key pair for OAuth2 JWT signing and verification.
     *
     * @deprecated OAuth now uses opaque tokens which do not require RSA keys.
     *             Only the encryption key is needed (see oauth:generateencryptionkey).
     *
     * Keys are stored in Data/Persistent/OAuth/. The private key is used
     * to sign access tokens; the public key is used to verify them.
     */
    public function generateKeysCommand(): void
    {
        $this->outputLine('<comment>This command is deprecated. OAuth now uses opaque tokens which do not require RSA keys.</comment>');
        $this->outputLine('<comment>Only the encryption key is needed (see oauth:generateencryptionkey).</comment>');
        $this->outputLine();
        $config = [
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $privateKey = openssl_pkey_new($config);
        if ($privateKey === false) {
            $this->outputLine('<error>Failed to generate private key</error>');
            $this->quit(1);
        }

        openssl_pkey_export($privateKey, $privateKeyPem);

        $publicKey = openssl_pkey_get_details($privateKey);
        $publicKeyPem = $publicKey['key'];

        $keyDir = FLOW_PATH_ROOT . 'Data/Persistent/OAuth';
        if (!\is_dir($keyDir)) {
            \mkdir($keyDir, 0700, true);
        }

        $privateKeyPath = $keyDir . '/private.key';
        $publicKeyPath = $keyDir . '/public.key';

        \file_put_contents($privateKeyPath, $privateKeyPem);
        \chmod($privateKeyPath, 0600);
        \file_put_contents($publicKeyPath, $publicKeyPem);

        $this->outputLine('<success>OAuth keys generated successfully:</success>');
        $this->outputLine('  Private key: %s', [$privateKeyPath]);
        $this->outputLine('  Public key:  %s', [$publicKeyPath]);
    }

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
