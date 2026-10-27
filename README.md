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

**Infrastructure configured and validated.** Application features and architectural examples will be added incrementally.
