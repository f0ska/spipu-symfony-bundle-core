# Spipu Core Bundle

The **CoreBundle** is the foundation of the Spipu bundle suite. It provides shared infrastructure that all other bundles depend on: bundle base class, encryption, async command execution, mail sending, role hierarchy, fixtures, and asset management.

## Documentation

- [Installation](./install.md)
- [Services Reference](./services.md)

## Features

- **AbstractBundle** — base class for all Spipu bundles (auto-loads `services.yaml`, exposes role hierarchy)
- **Encryptor** — asymmetric encryption via PHP Sodium (libsodium)
- **AsynchronousCommand** — run Symfony console commands in background processes
- **MailManager** — send HTML/text emails via Twig templates or raw HTML
- **Environment** — application environment descriptor (`dev` / `preprod` / `prod`)
- **RoleDefinitionInterface** — contract for contributing RBAC roles to the hierarchy
- **RoleDefinitionList** — runtime registry of all contributed role definitions
- **HasherFactory** — factory for creating a `NativePasswordHasher`
- **FixtureInterface / ListFixture** — database fixture loading system
- **Assets / AssetInterface** — bundle asset publishing (vendor, URL, or ZIP sources)
- **Slugger** — URL-safe slug generation (ASCII, lowercase)
- **Filesystem / FinderFactory** — testable wrappers around Symfony Filesystem and Finder
- **ConnectionQuoterFactory / ConnectionQuoterInterface** — DBAL identifier and value quoting, abstracts the deprecated `Connection::quoteIdentifier()` (DBAL 4.4+) and provides safe value quoting with type validation

## Requirements

- PHP 8.1+
- Symfony 6.4+
- `ext-sodium` (libsodium PHP extension)

## Test Utilities

The bundle also ships two test-helper classes used across the whole Spipu bundle suite:

- `Spipu\CoreBundle\Tests\SymfonyMock` — static factory for mocked Symfony services (container, router, mailer, Twig, security, Doctrine, form factory, console I/O, etc.)
- `Spipu\CoreBundle\Tests\SpipuCoreMock` — static factory for mocked CoreBundle services (`Filesystem`, `FinderFactory`, `MailManager`)

## Quick Start

```bash
composer require spipu/core-bundle
```

Generate and save an encryption key pair:

```bash
php bin/console spipu:encryptor:generate-key-pair
```

Save the output in your `.env.local` as `APP_ENCRYPTOR_KEY_PAIR=<generated_value>`.

[Full installation instructions](./install.md)
