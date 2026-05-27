# Core Bundle — Services Reference

[back](./README.md)

## Encryptor

Asymmetric encryption using PHP's libsodium extension (`ext-sodium`).

**Service:** `Spipu\CoreBundle\Service\EncryptorInterface`

```php
use Spipu\CoreBundle\Service\EncryptorInterface;

class MyService
{
    public function __construct(private EncryptorInterface $encryptor) {}

    public function store(string $secret): string
    {
        return $this->encryptor->encrypt($secret); // returns base64-encoded ciphertext
    }

    public function retrieve(string $ciphertext): ?string
    {
        return $this->encryptor->decrypt($ciphertext); // returns null if decryption fails
    }
}
```

**Notes:**
- Uses `sodium_crypto_box_seal` (anonymous public-key encryption).
- The key pair must be set via the `spipu.core.encryptor.key_pair` DI parameter (see [install.md](./install.md)).
- `decrypt()` returns `null` when decryption fails due to a wrong ciphertext.
- `decrypt()` throws `EncryptorException` when the key pair itself is invalid.
- `encrypt()` throws `EncryptorException` on a libsodium error.

---

## AsynchronousCommand

Launches a Symfony console command in a **non-blocking background process**.

**Service:** `Spipu\CoreBundle\Service\AsynchronousCommand`

```php
use Spipu\CoreBundle\Service\AsynchronousCommand;

class MyService
{
    public function __construct(private AsynchronousCommand $asyncCommand) {}

    public function triggerJob(int $id): void
    {
        // Runs: php bin/console my:command --id=123 >> logs/async.log 2>&1
        $this->asyncCommand->execute('my:command', ['--id=' . $id]);
    }
}
```

**Methods:**

| Method | Description |
|--------|-------------|
| `execute(string $command, array $parameters): bool` | Launch a command asynchronously (fire-and-forget) |
| `create(string $command, array $parameters): Process` | Build the `Process` object without starting it |
| `setDisableLog(bool $disableLog): self` | Disable output redirection to log file (output goes to `/dev/null`) |

**Notes:**
- String parameters are automatically shell-escaped with `escapeshellarg()`.
- The command line is built as: `{phpBin} bin/console {command} {parameters} >> {logsDir}/{logFilename} 2>&1`
- Each call also appends a timestamped entry to the log file before starting the process.
- `execute()` sets the `create_new_console` process option so the child process outlives the PHP request.
- `execute()` throws `AsynchronousCommandException` if the OS cannot launch a new process.

---

## MailManager

Sends HTML+plaintext emails using Twig templates or raw HTML bodies.

**Service:** `Spipu\CoreBundle\Service\MailManager`

```php
use Spipu\CoreBundle\Service\MailManager;
use Spipu\CoreBundle\Model\MailHeader;

class MyService
{
    public function __construct(private MailManager $mailManager) {}

    public function sendWelcome(string $to): void
    {
        $this->mailManager->sendTwigMail(
            subject: 'Welcome!',
            sender: 'no-reply@example.com',
            receiver: $to,                        // string, Address, or array of those
            twigTemplate: 'emails/welcome.html.twig',
            twigParameters: ['name' => 'Alice'],
            headers: [new MailHeader('X-Mailer', 'MyApp')]  // optional
        );
    }

    public function sendRaw(string $to): void
    {
        $this->mailManager->sendHtmlMail(
            subject: 'Hello',
            sender: 'no-reply@example.com',
            receiver: $to,
            body: '<p>Hello world</p>',
            headers: []
        );
    }
}
```

**Methods:**

| Method | Description |
|--------|-------------|
| `sendTwigMail(string $subject, string $sender, mixed $receiver, string $twigTemplate, array $twigParameters = [], array $headers = []): void` | Render a Twig template and send as HTML+plaintext email |
| `sendHtmlMail(string $subject, string $sender, mixed $receiver, string $body, array $headers = []): void` | Send a raw HTML string as HTML+plaintext email |
| `sendMail(Email $message): void` | Send a pre-built Symfony `Email` object |
| `prepareTwigMailMessage(...): Email` | Build an `Email` from a Twig template without sending |
| `prepareHtmlMailMessage(...): Email` | Build an `Email` from raw HTML without sending |
| `prepareEmailAddresses(mixed $values): array` | Parse and validate a receiver value into an array of `Address` |

**Notes:**
- `$receiver` accepts a comma-separated string (`MailManager::MAIL_SEPARATOR = ','`), a `Symfony\Component\Mime\Address` object, or an array of strings/`Address` objects.
- All emails are sent with `Email::PRIORITY_HIGH`.
- The plaintext part is generated automatically via `strip_tags()` on the HTML body.
- `MailHeader` (`Spipu\CoreBundle\Model\MailHeader`) is a simple value object wrapping a header key and value string; pass instances in the `$headers` array.

---

## RoleDefinitionInterface

Implement this interface to contribute RBAC roles to the shared role hierarchy.

```php
use Spipu\CoreBundle\Service\RoleDefinitionInterface;
use Spipu\CoreBundle\Entity\Role\Item;

class MyRoleDefinition implements RoleDefinitionInterface
{
    public function buildDefinition(): void
    {
        Item::load('ROLE_MY_FEATURE_VIEW')
            ->setLabel('my.role.view')      // translation key
            ->setWeight(10)                  // display order
            ->addChild('ROLE_ADMIN');        // inherits from ROLE_ADMIN

        Item::load('ROLE_MY_FEATURE_EDIT')
            ->setLabel('my.role.edit')
            ->setWeight(20)
            ->addChild('ROLE_ADMIN');

        Item::load('ROLE_MY_FEATURE')
            ->setLabel('my.role.manage')
            ->setWeight(100)
            ->addChild('ROLE_MY_FEATURE_VIEW')
            ->addChild('ROLE_MY_FEATURE_EDIT');
    }
}
```

Then register it in your bundle's `getRolesHierarchy()`:

```php
class MyBundle extends AbstractBundle
{
    public function getRolesHierarchy(): ?RoleDefinitionInterface
    {
        return new MyRoleDefinition();
    }
}
```

Non-bundle DI extensions can also contribute roles by implementing `RolesHierarchyBundleInterface`
directly on the extension class (see `AppExtension` in the app layer).

**`Item` fluent API:**

| Method | Description |
|--------|-------------|
| `Item::load(string $code): Item` | Load (or create) a role item from the global registry |
| `setLabel(string $label): self` | Translation key for the role label |
| `setWeight(int $weight): self` | Sort weight for display (lower = first) |
| `setType(string $type): self` | `Item::TYPE_ROLE` (default) or `Item::TYPE_PROFILE` |
| `setPurpose(?string $purpose): self` | Scope for filtering; default `'admin'`; set `null` to hide from lists |
| `addChild(string $code): self` | Add an inherited role |
| `getChildren(): Item[]` | Return child items (used by `SpipuCoreBundle` to build `role_hierarchy`) |
| `Item::getAll(): Item[]` | Return all registered items |
| `Item::resetAll(): void` | Clear the global registry (used in tests) |

---

## FixtureInterface / ListFixture

Use fixtures to load initial or test data into the database.

**Implement `FixtureInterface`:**

```php
use Spipu\CoreBundle\Fixture\FixtureInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MyFixture implements FixtureInterface
{
    public function getCode(): string { return 'my-fixture'; }
    public function getOrder(): int { return 10; }

    public function load(OutputInterface $output): void
    {
        $output->writeln('Loading my fixture...');
        // Insert data using Doctrine or direct SQL
    }

    public function remove(OutputInterface $output): void
    {
        $output->writeln('Removing my fixture...');
        // Delete the fixture data
    }
}
```

**Register it as a service** (tag `spipu.fixture`):

```yaml
# services.yaml
App\Fixture\MyFixture:
    tags:
        - { name: spipu.fixture }
```

**Notes:**
- `getCode()` must return a unique string identifier (used as the array key in `ListFixture`).
- Fixtures are sorted by `getOrder()` (ascending) before being loaded or removed.
- `ListFixture` (collected from all `spipu.fixture`-tagged services) is the entry point used by the commands.

**Load/remove via commands:**

```bash
php bin/console spipu:fixtures:load
php bin/console spipu:fixtures:remove
```

---

## AbstractController

Base controller for all Spipu bundle controllers. Extends Symfony's `AbstractController` and adds translation helpers.

**Class:** `Spipu\CoreBundle\Controller\AbstractController`

All controllers in Spipu bundles should extend this class instead of Symfony's `AbstractController` directly.

```php
use Spipu\CoreBundle\Controller\AbstractController;

class MyController extends AbstractController
{
    public function myAction(): Response
    {
        // Translate with the default domain
        $message = $this->trans('my.translation.key');

        // Translate with a specific domain
        $message = $this->trans('Invalid credentials.', [], 'security');

        // Add a translated flash message
        $this->addFlashTrans('success', 'my.success.message');

        // Add a translated flash message with a specific domain
        $this->addFlashTrans('danger', 'Invalid credentials.', [], 'security');
    }
}
```

**Methods:**

| Method | Description |
|--------|-------------|
| `trans(string $message, array $params = [], ?string $domain = null): string` | Translate a message key |
| `addFlashTrans(string $type, string $message, array $params = [], ?string $domain = null): void` | Add a translated flash message |

---

## AbstractBundle

The base class for all Spipu bundles. Extends Symfony's `AbstractBundle` and adds:

- Auto-loading of `config/services.yaml` from the bundle directory (via `loadExtension()`).
- Implementation of `RolesHierarchyBundleInterface` — override `getRolesHierarchy()` to contribute roles.

```php
use Spipu\CoreBundle\AbstractBundle;
use Spipu\CoreBundle\Service\RoleDefinitionInterface;

class MyBundle extends AbstractBundle
{
    public function getRolesHierarchy(): ?RoleDefinitionInterface
    {
        return new MyRoleDefinition(); // or null if no custom roles
    }
}
```

**`SpipuCoreBundle` role hierarchy bootstrap:**

`SpipuCoreBundle` itself implements `prependExtension()` to collect role definitions from all registered
bundles that implement `RolesHierarchyBundleInterface` and injects the merged `role_hierarchy` into
the Symfony Security configuration automatically. This means:
- You do **not** need to manually configure `security.role_hierarchy` in your security YAML.
- Non-bundle DI extensions (e.g. `AppExtension`) can also contribute roles by implementing
  `RolesHierarchyBundleInterface`.

Built-in roles contributed by `SpipuCoreBundle` itself:

| Role | Label key | Type | Weight |
|------|-----------|------|--------|
| `ROLE_USER` | `spipu.core.role.user` | profile | 10 |
| `ROLE_ADMIN` | `spipu.core.role.admin` | profile | 50 |
| `ROLE_SUPER_ADMIN` | `spipu.core.role.super_admin` | profile | 90 |

---

## RoleDefinitionList

Runtime registry of all `RoleDefinitionInterface` implementations tagged `spipu.user.role`.

**Service:** `Spipu\CoreBundle\Service\RoleDefinitionList`

| Method | Description |
|--------|-------------|
| `getDefinitions(): RoleDefinitionInterface[]` | Return all registered role definitions |
| `buildDefinitions(): void` | Call `buildDefinition()` on every definition (idempotent) |
| `getItems(?string $purpose, ?string $type): Item[]` | Return filtered `Item` objects from the global registry |

This service is used by other bundles (e.g. UiBundle) to display role selection lists in the UI.

---

## Environment

Describes the application environment with a human-readable name and Bootstrap color.

**Service:** `Spipu\CoreBundle\Service\EnvironmentInterface`

| Method | Description |
|--------|-------------|
| `getCurrentCode(): string` | Returns `'dev'`, `'preprod'`, or `'prod'` |
| `getCurrentName(): string` | Returns `'Development'`, `'PreProduction'`, or `'Production'` |
| `getCurrentColor(): string` | Returns a Bootstrap color string (`'secondary'`, `'danger'`, `'primary'`) |
| `setColor(string $code, string $color): void` | Register a custom Bootstrap color for an environment code |
| `isProduction(): bool` | `true` when code is `'prod'` |
| `isPreproduction(): bool` | `true` when code is `'preprod'` |
| `isDevelopment(): bool` | `true` when code is `'dev'` |
| `getEnvironmentSuffix(): string` | Returns `''` in prod, or `' [dev]'` / `' [preprod]'` otherwise |

> **Note:** `Environment` is not wired automatically by `CoreBundle`. If you need it, define it as a
> service in your application and inject the current Symfony env string as `$currentCode`.

---

## HasherFactory

Factory that creates a `NativePasswordHasher` instance.

**Service:** `Spipu\CoreBundle\Service\HasherFactory`

```php
use Spipu\CoreBundle\Service\HasherFactory;

class MyService
{
    public function __construct(private HasherFactory $hasherFactory) {}

    public function hashSecret(string $plain): string
    {
        $hasher = $this->hasherFactory->create(); // returns PasswordHasherInterface
        return $hasher->hash($plain);
    }
}
```

---

## Slugger / SluggerInterface

Generates URL-safe, ASCII, lowercase slugs.

**Service:** `Spipu\CoreBundle\Service\SluggerInterface`

```php
use Spipu\CoreBundle\Service\SluggerInterface;

class MyService
{
    public function __construct(private SluggerInterface $slugger) {}

    public function makeSlug(string $title): string
    {
        return $this->slugger->slug($title);           // e.g. "hello-world"
        // $this->slugger->slug($title, '_');          // custom separator
        // $this->slugger->slug($title, '-', 'fr');    // locale hint
    }
}
```

The implementation uses Symfony's `AsciiSlugger` with `'en'` locale and forces lowercase output.

---

## Filesystem

Extends `Symfony\Component\Filesystem\Filesystem` with convenience methods.

**Service:** `Spipu\CoreBundle\Service\Filesystem`

| Method | Description |
|--------|-------------|
| `isDir(string $filename): bool` | Returns `true` only if the path exists AND is a directory |
| `isFile(string $filename): bool` | Returns `true` only if the path exists AND is a regular file |
| `getContent(string $filename): string` | Returns the file contents (`file_get_contents`) |
| `unZip(string $zipFilename, string $folderDestination): bool` | Extracts a ZIP archive to a folder |

All methods inherited from Symfony's `Filesystem` are also available.

---

## FinderFactory

Testable factory for `Symfony\Component\Finder\Finder`.

**Service:** `Spipu\CoreBundle\Service\FinderFactory`

```php
use Spipu\CoreBundle\Service\FinderFactory;

class MyService
{
    public function __construct(private FinderFactory $finderFactory) {}

    public function listFiles(string $dir): iterable
    {
        return $this->finderFactory->create()->files()->in($dir);
    }
}
```

Inject `FinderFactory` instead of instantiating `Finder` directly so the factory can be mocked in tests.

---

## ConnectionQuoterFactory / ConnectionQuoter

Wraps DBAL identifier and value quoting behind a stable interface. Useful when building raw SQL queries from variable identifiers (table or column names) or values.

The factory creates a `ConnectionQuoterInterface` bound to a specific `Doctrine\DBAL\Connection`. The quoter delegates identifier quoting to `AbstractPlatform::quoteSingleIdentifier()` (works across all DBAL 3.x and 4.x versions) and value quoting to `Connection::quote()`, with explicit type validation.

**Service:** `Spipu\CoreBundle\Service\ConnectionQuoterFactoryInterface`

```php
use Doctrine\DBAL\Connection;
use Spipu\CoreBundle\Service\ConnectionQuoterFactoryInterface;

class MyService
{
    public function __construct(private ConnectionQuoterFactoryInterface $quoterFactory) {}

    public function buildQuery(Connection $connection, string $table, array $rowValues): string
    {
        $quoter = $this->quoterFactory->create($connection);

        return sprintf(
            'INSERT INTO %s VALUES (%s)',
            $quoter->quoteIdentifier($table),
            $quoter->quoteValues($rowValues)
        );
    }
}
```

**`ConnectionQuoterFactoryInterface`:**

| Method | Description |
|--------|-------------|
| `create(Connection $connection): ConnectionQuoterInterface` | Build a quoter bound to the given DBAL connection |

**`ConnectionQuoterInterface`:**

| Method | Description |
|--------|-------------|
| `quoteIdentifier(string $identifier): string` | Quote a (possibly qualified) identifier — splits on `.` and quotes each part (`my_schema.my_table` → `` `my_schema`.`my_table` ``) |
| `quoteSingleIdentifier(string $identifier): string` | Quote a single identifier part — does not split on `.` (use for column or table names known to contain no dot) |
| `quoteValue(mixed $value): string` | Quote a scalar value as a SQL literal (handles `null` → `'NULL'`, `bool` → `'0'`/`'1'`, `int`/`float` → cast, `string`/`Stringable` → `Connection::quote()`) |
| `quoteValues(array $values): string` | Quote each item with `quoteValue()` and join with `,` (for `IN (...)` clauses or row tuples); throws on empty array |

**Notes:**
- `quoteValue()` throws `ConnectionQuoterException` for `array`, `resource`, or non-`Stringable` objects — failing fast on misuse rather than silently producing broken SQL.
- `quoteValues()` throws `ConnectionQuoterException` on an empty array (an empty `IN ()` would produce invalid SQL).
- For high-throughput row loops (millions of rows), prefer an inline switch over `quoteValue()` — the method call overhead is measurable. See `ProcessBundle::ImportFileToTable` for an example of the inline pattern.

**Exception:** `Spipu\CoreBundle\Exception\ConnectionQuoterException` — thrown by `quoteValue()` and `quoteValues()` on unsupported input.

---

## Assets / AssetInterface

Publishes static assets from bundles (or external URLs/ZIPs) into the application's `public/bundles/` directory.

**Service:** `Spipu\CoreBundle\Service\Assets`

Assets are registered by implementing `AssetInterface` and tagging the service `spipu.asset`:

```php
use Spipu\CoreBundle\Assets\AssetInterface;

class MyBundleAsset implements AssetInterface
{
    public function getCode(): string { return 'my-bundle'; }       // lowercase alphanumeric + dash
    public function getSourceType(): string { return self::TYPE_VENDOR; } // or TYPE_URL, TYPE_URL_ZIP
    public function getSource(): string { return 'vendor/package'; } // vendor path or URL
    public function getMapping(): array
    {
        return [
            'dist/css' => 'css',   // source relative path => destination subdirectory
            'dist/js'  => 'js',
        ];
    }
}
```

Source types:

| Constant | Value | Description |
|----------|-------|-------------|
| `AssetInterface::TYPE_VENDOR` | `'vendor'` | Copy from `{projectDir}/vendor/{source}/` |
| `AssetInterface::TYPE_URL` | `'url'` | Download individual files from a base URL |
| `AssetInterface::TYPE_URL_ZIP` | `'zip'` | Download a ZIP archive, extract, then copy |

Register in `services.yaml`:

```yaml
App\Assets\MyBundleAsset:
    tags:
        - { name: spipu.asset }
```

Install via command:

```bash
php bin/console spipu:assets:install          # installs into public/bundles/{code}/
php bin/console spipu:assets:install public   # explicit target directory
```

[back](./README.md)
