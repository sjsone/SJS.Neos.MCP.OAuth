# SJS.Neos.MCP.OAuth

OAuth 2.0 authentication layer for the Neos MCP server. Enables MCP clients (Claude Desktop, VS Code, etc.) to authenticate against the Neos backend using the standard Authorization Code flow with PKCE.

Built on [league/oauth2-server](https://oauth2.thephpleague.com/) using opaque tokens — no JWT signing key management required.

---

## How It Works

```graph
MCP Client                      Neos CMS                     MCP Server
    │                               │                            │
    │  1. GET /.well-known/         │                            │
    │     oauth-authorization-server│                            │
    │ ──────────────────────────────>                            │
    │                               │                            │
    │  2. POST /oauth/mcp/register  │                            │
    │     (auto-register client)    │                            │
    │ ──────────────────────────────>                            │
    │                               │                            │
    │  3. GET /oauth/mcp/authorize  │                            │
    │     (redirects to Neos login  │                            │
    │      if not authenticated)    │                            │
    │ ──────────────────────────────>                            │
    │                               │                            │
    │  4. POST /oauth/mcp/token     │                            │
    │     (exchange code → token)   │                            │
    │ ──────────────────────────────>                            │
    │                               │                            │
    │  5. POST /mcp  Bearer <token> │                            │
    │ ──────────────────────────────────────────────────────────>│
```

When an unauthenticated user hits the authorize endpoint, they are redirected to the Neos login page. After login, an AOP aspect (`LoginRedirectAspect`) redirects them back to complete the OAuth flow seamlessly.

---

## Endpoints

### RFC 8414 — Authorization Server Metadata

| Route | Method | Description |
| --- | --- | --- |
| `/.well-known/oauth-authorization-server` | GET | Server metadata discovery (endpoints, scopes, grant types) |

### OAuth 2.0 Endpoints

| Route | Method | Description |
| --- | --- | --- |
| `/oauth/mcp/authorize` | GET, POST | Authorization endpoint (Authorization Code flow) |
| `/oauth/mcp/token` | POST | Token endpoint (exchange code for access/refresh tokens) |
| `/oauth/mcp/register` | POST | Dynamic Client Registration (RFC 7591) |

All OAuth endpoints are accessible to `Neos.Flow:Everybody` — authentication happens at the authorize step via the Neos backend login.

---

## Configuration

### Quick Setup

```bash
# Generate an encryption key
./flow oauth:generateEncryptionKey

# Add the output to your Settings.SJS.Flow.MCP.yaml
```

---

## Token Format

The server uses **opaque tokens** (random strings stored in the database) rather than JWTs. This means:

- No RSA key pair management — only the encryption key is needed
- Tokens are validated by database lookup via `OpaqueTokenValidator`
- Revocation is instant (delete from DB) — no token expiry race conditions
- `OAuthConnectionProvider` resolves opaque tokens to MCP `Connection` objects for the MCP server

### Token Lifecycle

1. Client authenticates via Authorization Code flow → receives `access_token` + `refresh_token`
2. `access_token` is used as `Authorization: Bearer <token>` on MCP requests
3. Expired access tokens can be refreshed via the refresh token grant
4. Tokens can be revoked through the backend module

---

## Connection Provider Chain

This package replaces the default `ConnectionProviderInterface` with `ChainedConnectionProvider` (configured in `Objects.yaml`):

```
Incoming Bearer token
  → ChainedConnectionProvider
    → OAuthConnectionProvider (tries opaque OAuth token lookup first)
    → PersistentConnectionProvider (falls back to static token config)
```

This means existing static-token configurations continue to work alongside OAuth.

---

## Backend Modules

### MCP OAuth Manager (`/neos/administration/mcp/oauthClientModule`)

Admin module for managing all OAuth clients across all users. Requires `Neos.Neos:Administrator` role.

### OAuth Clients (`/neos/mcp/oauthClients`)

User-facing module where editors can manage their own OAuth client applications. Requires `Neos.Neos:AbstractEditor` role.

Both modules support: create, edit, delete clients, regenerate secrets, list active tokens, and revoke tokens.

---

## CLI Commands

| Command | Description |
| --- | --- |
| `./flow oauth:generateEncryptionKey` | Generate a hex-encoded 32-byte encryption key |

---

## Related

- **[SJS.Neos.MCP](https://github.com/sjsone/SJS.Neos.MCP)** — Core MCP server (provides the `/mcp` endpoint this package protects)
- **[SJS.Flow.MCP](https://github.com/sjsone/SJS.Flow.MCP)** — Flow framework MCP abstractions (server, tools, FeatureSets)
