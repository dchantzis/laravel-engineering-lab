# Laravel Engineering Lab

A Laravel application for exploring, implementing, testing, and documenting software engineering patterns and reusable components.

The goal is to develop **working implementations**, not just isolated code samples. Each feature should have a clear use case, automated tests, and documentation explaining the design decisions and trade-offs.

## Scope

The lab will cover two complementary areas:

- **Application architecture:** service and repository layers, dependency injection, domain modelling, transactions, events, queues, and integration boundaries.
- **Reusable components:** request tracing, API auditing, HTTP API consumers, feature flags, idempotency, caching, and related cross-cutting concerns.

Features will be introduced incrementally. Some may eventually be extracted into independently versioned Composer packages when they have a stable interface, meaningful reuse potential, and sufficient tests. Not every architectural pattern needs to become a package.

## Technology

The local development environment includes:

| Component | Technology |
| --- | --- |
| Application | Laravel / PHP 8.5 |
| Web server | Nginx and PHP-FPM |
| Primary database | MySQL 8.4 |
| Testing database | SQLite |
| Cache / messaging infrastructure | Redis |
| Dependency management | Composer and npm |
| Debugging | Xdebug |
| Process management | Supervisor |
| Development environment | Docker Compose |

> The infrastructure can run before Laravel is installed. The `application/` directory contains the Laravel source once the application has been created.

## Implemented features

The lab contains working, tested implementations rather than only architectural examples:

| Area | Implementation |
| --- | --- |
| OAuth2 authentication | Laravel Passport Client Credentials for integrations; Authorization Code + PKCE for administrator access, with separate authorization middleware. |
| API audit logging | Correlated request/response records, including asynchronous response handling; sensitive content is sanitized before persistence and audit content/metadata are encrypted at rest. |
| HTTP request tracing | Opt-in `ip_trace` middleware and persistence, including request-body handling and sensitive-field redaction. |
| System logging | Database-backed structured logs, sensitive context redaction, encryption at rest, and correlation with request and console execution identifiers. |
| Console command monitoring | Execution records, results, timings, failure reporting, notifications, and heartbeat tracking. |
| Scheduler lock diagnostics | Read-only lock inspection and guarded release of orphaned locks with checks against active/newer executions. |
| API response debugging | Configurable debug details with separate sensitive-data sanitization controls. |

### Sensitive-data handling

`SensitiveDataSanitizer` provides centralized, recursive redaction of configured sensitive fields in arrays, JSON, form-encoded content, HTTP headers, and URL query parameters. Audit payloads with unsupported or unstructured formats are omitted rather than stored verbatim. Redaction occurs **before** sensitive audit content is encrypted and persisted.

The relevant settings are under `engineering.sensitive_data` in `application/config/engineering.php`:

- `enabled` (`SANITIZE_SENSITIVE_DATA`): controls general sanitization outside production.
- `sanitize_api_debug_information` (`SANITIZE_API_DEBUG_INFORMATION`): independently controls sanitization of API debug output outside production.
- `fields`: configured sensitive field names, matched case-insensitively.

Both switches default to enabled. **Production enforces sanitization even when a switch is disabled.** The configuration is intended for development and testing; do not disable sanitization when processing real credentials.

### Automated tests

Run the complete suite with:

```bash
./dev test
```

The suite covers unit behavior, database persistence, OAuth2 authentication/authorization, real HTTP request/response auditing, sensitive-data handling, command execution, and scheduler-lock safeguards. At the latest verified run, **212 tests passed** using the SQLite test environment.

## Getting started

### Prerequisites

- Docker Engine with the Docker Compose plugin
- Bash

### Configure the environment

```bash
cp .env.example .env
chmod +x ./dev
```

Review `.env` before starting. The local ports and Docker project identifiers must be unique on your machine.

### Build and start

```bash
./dev check
./dev build
./dev start
./dev status
./dev app ip
```

The application address is reported by `./dev app ip` (configured through `APP_HOST` and `APP_PORT`).

### Install Laravel

If `application/` is still empty, create the Laravel project there. The directory must contain no existing files, including `.gitkeep`, before running `create-project`:

```bash
rm application/.gitkeep
./dev composer create-project laravel/laravel .
```

The Composer command runs inside the application container, whose working directory is `/var/www` and is bind-mounted to `application/`.

After installation, configure Laravel's `application/.env` for the container network. Use `database` as the MySQL host and `redis` as the Redis host; use their **container ports**, not the host-published ports. Then run the necessary application setup and migrations.

## Authentication & OAuth2

The application uses **Laravel Passport** for two distinct OAuth2 flows:

| Consumer | OAuth2 grant | Identity | API middleware |
| --- | --- | --- | --- |
| External integration | Client Credentials | OAuth client (no user) | `CheckClientCredentials` |
| Administrator | Authorization Code + PKCE | Authenticated `users` record | `auth:api`, `EnsureUserIsAdmin` |

Both flows use the same Passport installation. Client Credentials tokens must not be used to authenticate administrator endpoints. The admin browser login uses Laravel's `web` session guard; Passport separately issues access tokens for API requests.

### Install and initialize Passport

From the repository root, after the application and its database are configured:

```bash
./dev composer require laravel/passport
./dev artisan passport:install
./dev db migrate
```

**Existing installations:** Do not rerun `passport:install` or regenerate Passport keys unnecessarily; doing so can invalidate existing tokens. A fresh clone with committed application code normally needs `./dev composer install`, the database migrations, and Passport signing keys generated for that environment. Check the current Passport version's installation commands before using these steps on an existing environment.

The application uses the Passport `api` guard backed by the `users` provider. The `User` model must include `Laravel\Passport\HasApiTokens`. Browser sessions use the `web` guard. The administrator API checks `is_admin` through `AdminAuthorizationService` and `EnsureUserIsAdmin` middleware.

### Configure token lifetimes

Set the following in **`application/.env`** (not the repository-root Docker `.env`):

```dotenv
PASSPORT_TOKENS_EXPIRE_IN_HOURS=1
PASSPORT_REFRESH_TOKENS_EXPIRE_IN_HOURS=24
PASSPORT_PERSONAL_ACCESS_TOKENS_EXPIRE_IN_HOURS=24
```

`config/auth.php` reads these values under the `passport` key. `App\Providers\AuthServiceProvider` configures Passport with `tokensExpireIn`, `refreshTokensExpireIn`, and `personalAccessTokensExpireIn`; the provider is registered in `bootstrap/providers.php`.

```bash
./dev artisan config:clear
```

The one-hour access-token lifetime applies to both flows. Client Credentials tokens have **no refresh token**: integrations request a new access token using their client credentials. The new expiration settings apply to newly issued tokens only.

### Create an integration OAuth2 client

```bash
./dev artisan passport:client --client
```

Follow the prompts, give the client a descriptive name, and securely save the generated **client ID and client secret**. Do not commit either secret-bearing configuration or issued tokens to Git.

To request a machine-to-machine access token, replace the placeholders with the client credentials:

```bash
curl -sS -X POST http://127.0.0.1:8092/oauth/token \
  -H 'Accept: application/json' \
  -d 'grant_type=client_credentials' \
  -d 'client_id=YOUR_INTEGRATION_CLIENT_ID' \
  -d 'client_secret=YOUR_INTEGRATION_CLIENT_SECRET'
```

The response should include a `Bearer` access token and `expires_in: 3600` with the example configuration. Use it on an integration API endpoint with `Authorization: Bearer <access_token>`. Integration routes use the Client Credentials middleware, not `auth:api` (which expects a user).

### Provision an administrator

Use the project's interactive system-user command:

```bash
./dev artisan engineering:system-user
```

Select **Create**, enter the administrator's name and email, and enable administrator access when prompted. The command generates a password and displays it once; store it securely. The command also supports updating an existing user. The `is_admin` flag defaults to `false` and is not mass assignable.

### Create an administrator OAuth2 client (PKCE)

```bash
./dev artisan passport:client --public
```

Use a descriptive name (for example, `Engineering Lab Admin`) and configure the callback URL for the admin frontend. During local testing, the callback used was:

```text
http://127.0.0.1:8092/admin/callback
```

This is a **public client**: it does not use a client secret. Record the generated client ID. The callback is a temporary testing target until a frontend implements it.

### Browser login and authorization approval

The application provides `GET /login`, `POST /login`, and `POST /logout` through `AuthenticationController`. `LoginRequest` validates credentials; `AuthenticationService` authenticates with the `web` guard and rejects non-admin users. Successful login regenerates the session.

Passport's `/oauth/authorize` endpoint requires a configured authorization approval view. The project registers `Passport::authorizationView('auth.oauth-authorize')` and renders the approval form in `resources/views/auth/oauth-authorize.blade.php`.

### Test Authorization Code + PKCE

1. Generate a PKCE verifier and S256 challenge locally:

   ```bash
   php -r '
   $verifier = rtrim(strtr(base64_encode(random_bytes(32)), "+/", "-_"), "=");
   $challenge = rtrim(strtr(base64_encode(hash("sha256", $verifier, true)), "+/", "-_"), "=");
   echo "Verifier: $verifier\nChallenge: $challenge\n";
   '
   ```

2. Construct a browser authorization URL using your client ID, redirect URI, challenge, and a random `state` value:

   ```text
   http://127.0.0.1:8092/oauth/authorize?client_id=YOUR_ADMIN_CLIENT_ID&redirect_uri=URL_ENCODED_CALLBACK&response_type=code&scope=&state=RANDOM_STATE&code_challenge=YOUR_CHALLENGE&code_challenge_method=S256
   ```

3. Open the URL, log in as the administrator if prompted, and approve the application. Passport redirects to the registered callback with `code` and `state` query parameters. Verify that `state` matches the value you generated. The temporary callback may return 404; for manual testing, retrieve the `code` from the address bar. **The authorization code is not the PKCE challenge.**

4. Exchange the one-time authorization code for tokens, using the **original verifier**:

   ```bash
   curl -sS -X POST http://127.0.0.1:8092/oauth/token \
     -H 'Accept: application/json' \
     -d 'grant_type=authorization_code' \
     -d 'client_id=YOUR_ADMIN_CLIENT_ID' \
     -d 'redirect_uri=http://127.0.0.1:8092/admin/callback' \
     -d 'code=YOUR_AUTHORIZATION_CODE' \
     -d 'code_verifier=YOUR_ORIGINAL_VERIFIER'
   ```

   Expect an access token, refresh token, and `expires_in: 3600` with the example configuration. Authorization codes expire quickly and are single-use.

5. Test a protected admin endpoint (the `/api/admin/test` route is a temporary development route and may be removed):

   ```bash
   read -rsp 'Access token: ' ACCESS_TOKEN; echo
   curl -i http://127.0.0.1:8092/api/admin/test \
     -H 'Accept: application/json' \
     -H "Authorization: Bearer $ACCESS_TOKEN"
   unset ACCESS_TOKEN
   ```

   An authorized administrator receives HTTP 200. `auth:api` resolves the user from the Passport token; `EnsureUserIsAdmin` enforces administrator authorization.

### Security and deployment notes

- The `127.0.0.1:8092` address and callback are **local-development examples**; use the actual host, HTTPS, and registered redirect URI in other environments.
- Never put tokens, client secrets, authorization codes, or PKCE verifiers into committed files, issue reports, or shared logs. Rotate credentials exposed outside a trusted local environment.
- The approval view and login form are intentionally minimal development interfaces, not a finished production admin frontend.
- Disable detailed exception traces and debug output in production; review request/audit logging for credential redaction.
- Administrator access-token lifetime is currently configured globally alongside integration access-token lifetime. Revisit lifetimes and client-specific policies before production use.

## Development commands

The `./dev` helper is the main interface for local development. Run `./dev help` for the complete command list.

| Command | Purpose |
| --- | --- |
| `./dev start` | Start services |
| `./dev stop` | Stop services without deleting volumes |
| `./dev restart` | Restart services |
| `./dev status` | Show container status |
| `./dev logs` | Follow application logs |
| `./dev app` | Open an application-container shell |
| `./dev app ip` | Show application host and port |
| `./dev php -v` | Show PHP version |
| `./dev artisan list` | Run an Artisan command |
| `./dev composer install` | Run Composer |
| `./dev npm install` | Run npm |
| `./dev assets build` | Build frontend assets |
| `./dev test` | Run tests using in-memory SQLite |
| `./dev test-mysql` | Run tests using the configured test environment |
| `./dev db cli` | Open the MySQL client |
| `./dev db migrate` | Run Laravel migrations |
| `./dev redis ping` | Check Redis connectivity |
| `./dev nginx configtest` | Validate Nginx configuration |
| `./dev nginx reload` | Reload Nginx configuration |
| `./dev nginx restart` | Restart Nginx through Supervisor |
| `./dev php-fpm status` | Inspect PHP-FPM process status |
| `./dev check` | Validate Docker Compose configuration |

**Note:** Commands such as `artisan`, `assets`, and `test` require the application and its dependencies to be installed. SQLite-based testing also requires the application's test configuration to be compatible with SQLite.

### Data persistence and cleanup

MySQL and Redis use Docker volumes. Ordinary `./dev stop` and `./dev start` operations preserve their data.

```bash
./dev purge
```

**Warning:** `purge` removes the project's containers **and persistent volumes**, including local database and Redis data. Use it only when you intend to discard that data.

## Repository layout

```text
.
├── application/                  # Laravel application source
├── docker-configuration/         # Dockerfile and service configuration
├── docker-compose.yml            # Local services
├── dev                           # Development command helper
├── .env.example                  # Local infrastructure configuration template
└── README.md
```

As implementations are added, their architecture, tests, and documentation should live alongside the application. A `docs/` directory can hold concept notes and architectural decision records.

## Engineering principles

1. **Start with a real use case.** Prefer an executable workflow over an abstract demonstration.
2. **Make behaviour testable.** Include unit and integration tests as appropriate.
3. **Document trade-offs.** Record why a design was chosen and what alternatives were considered.
4. **Use the framework deliberately.** Prefer built-in Laravel capabilities unless an abstraction provides clear additional value.
5. **Extract packages selectively.** Package only components with coherent boundaries and a demonstrated need for independent reuse.
6. **Keep sensitive data out of Git.** Never commit credentials, secrets, production data, or confidential source material.

## Status

**Active engineering lab.** Docker infrastructure, Passport authentication and authorization, observability/auditing, sensitive-data controls, console execution monitoring, and scheduler-lock diagnostics are implemented and covered by automated tests.

**Planned, not yet implemented:** HTTP API consumers, feature flags, idempotency, caching strategies, and rate limiting (including a design inspired by prior order-management work). Future additions will be scoped as independent, tested engineering exercises.
