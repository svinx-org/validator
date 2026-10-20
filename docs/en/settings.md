# Settings

`BaseSettings` reads the application configuration from the **environment** (environment variables and a `.env` file) and validates it with the same types and constraints as a model.

## Declaring settings

!!! example "docs_src/Settings/AppSettings.php"
    ```php
    namespace Svinx\Validator\Examples\Settings;

    use Svinx\Validator\Field;
    use Svinx\Validator\Settings\BaseSettings;
    use Svinx\Validator\Validators\EmailStr;
    use Svinx\Validator\Validators\SecretStr;

    class AppSettings extends BaseSettings
    {
        public static function env_prefix(): string
        {
            return 'APP_';
        }

        public static function env_nested_delimiter(): ?string
        {
            return '__';
        }

        public string $env = 'dev';

        public bool $debug = false;

        public int $port = 8080;

        public float $ratio = 1.0;

        public ?EmailStr $admin_email = null;

        public ?SecretStr $api_key = null;

        public ?NestedDbSettings $db = null;

        #[Field(alias: 'jwt_secret')]
        public ?string $token = null;
    }
    ```

## Source precedence

```
1. données explicites  new AppSettings(['port' => 9000])
2. variables d'environnement        APP_PORT=9000
3. fichier .env (env_file())
4. valeurs par défaut des propriétés
```

```php
use Svinx\Validator\Examples\Settings\AppSettings;

$settings = AppSettings::load([
    'APP_ENV'   => 'production',
    'APP_DEBUG' => 'true',
    'APP_PORT'  => '9000',
    'APP_RATIO' => '0.5',
    'APP_ADMIN_EMAIL' => 'admin@example.com',
]);

$settings->env;       // "production"
$settings->debug;     // true      (chaîne "true" convertie en booléen)
$settings->port;      // 9000      (chaîne "9000" convertie en int)
$settings->ratio;     // 0.5
$settings->admin_email->value();    // "admin@example.com"
```

```php
new AppSettings(['port' => 1234], ['APP_PORT' => '9000'])->port;   // 1234 (l'explicite gagne)
```

`load()` is a shortcut: `AppSettings::load($env)` ≡ `new AppSettings(null, $env)`.

!!! tip "Testing without touching the environment"
    The second argument of the constructor (and of `load()`) is a **simulated** environment: no `putenv()` is needed in your tests.

## Variable names

By default: `env_prefix()` + property name in `UPPER_SNAKE_CASE`.

| Property | Variable |
| --- | --- |
| `$env` | `APP_ENV` |
| `$admin_email` | `APP_ADMIN_EMAIL` |
| `$port` | `APP_PORT` |

A `#[Field(alias: ...)]` provides an alternative name:

```php
#[Field(alias: 'jwt_secret')]
public ?string $token;      // lu dans APP_JWT_SECRET
```

The property name is always accepted as a fallback (`APP_TOKEN`).

!!! note "Without a prefix, the system environment is ignored"
    `ENV` and `PORT` are not read by `AppSettings` (prefix `APP_`): this avoids collisions with the system environment (`PORT`, `DEBUG`...). Settings without a prefix do exist, but prefer to expose a prefix.

### Case sensitivity

By default, case is **ignored**: `APP_ENV` and `app_env` designate the same field.

```php
class CaseSensitiveSettings extends BaseSettings
{
    public static function case_sensitive(): bool
    {
        return true;
    }

    public string $ServiceName = 'default';
    public string $region = 'default';
}

CaseSensitiveSettings::load(['ServiceName' => 'custom'])->ServiceName;   // "custom"
CaseSensitiveSettings::load(['servicename' => 'ignored'])->ServiceName;  // "default"
```

!!! example "docs_src/Settings/CaseSensitiveSettings.php"

## Nested settings

With `env_nested_delimiter()`, a field whose type is another `BaseSettings` is built from the prefixed variables:

!!! example "docs_src/Settings/NestedDbSettings.php"
    ```php
    class NestedDbSettings extends BaseSettings
    {
        public static function env_prefix(): string
        {
            return 'SXDB_';
        }

        public string $host = 'localhost';
        public int $port = 5432;
        public bool $ssl = false;
    }
    ```

    ```php
    $settings = AppSettings::load([
    'APP_DB__HOST' => 'db.internal',
    'APP_DB__PORT' => '6432',
    'APP_DB__SSL'  => 'yes',
    ]);

    $settings->db;        // NestedDbSettings
    $settings->db->host;  // "db.internal"
    $settings->db->port;  // 6432
    $settings->db->ssl;   // true
    ```

Values passed explicitly keep precedence and are merged with the environment:

```php
new AppSettings(['db' => ['host' => 'explicit']])->db->host;   // "explicit"
```

## The `.env` file

`env_file()` returns the path of the file to load:

!!! example "docs_src/Settings/DotEnvSettings.php"
    ```php
    class DotEnvSettings extends BaseSettings
    {
        public static function env_prefix(): string
        {
            return 'DOTENV_';
        }

        public static function env_file(): ?string
        {
            return getenv('SVINX_ENV_FILE') ?: null;
        }

        public string $value = 'default';
        public int $count = 0;
        public string $quoted = '';
    }
    ```

    ```dotenv
    # un commentaire
    DOTENV_VALUE=from-file
    export DOTENV_COUNT=7
    DOTENV_QUOTED="valeur avec espaces"
    ```

    ```php
    putenv('SVINX_ENV_FILE=/chemin/vers/.env');

    $settings = new DotEnvSettings();

    $settings->value;    // "from-file"
    $settings->count;    // 7
    $settings->quoted;   // "valeur avec espaces"
    ```

The supported format is deliberately minimal:

| Syntax | Supported |
| --- | --- |
| `CLE=valeur` | ✔ |
| `export CLE=valeur` | ✔ |
| `#` comments and blank lines | ✔ |
| quoted values (`"..."` or `'...'`) | ✔ (escapes are interpreted inside double quotes) |
| variable interpolation, multi-line blocks | ✘ |

!!! warning "Source precedence"
    **Real** environment variables take precedence over the `.env` file. This is the expected behaviour (a `.env` file is a development default), and it is covered by the tests.

The file can also be parsed directly:

```php
BaseSettings::parseEnvFile('/chemin/vers/.env');   // array<string, string>
BaseSettings::readEnvironment('/chemin/vers/.env'); // .env + variables réelles
```

## Type coercion

Environment variables are **always strings**. Settings therefore validate in lax mode (`Type::lax()`):

| Variable | Property | Result |
| --- | --- | --- |
| `APP_PORT=9000` | `int $port` | `9000` |
| `APP_DEBUG=true` | `bool $debug` | `true` (`1`, `yes`, `on` accepted; `0`, `false`, `no`, `off` too) |
| `APP_RATIO=0.5` | `float $ratio` | `0.5` |
| `APP_PORT=abc` | `int $port` | ✘ `ValidationError` |

A classic model (`BaseModel`) **does not coerce**: coercion is specific to settings, or requested explicitly through `Type::lax()`.

## Secrets

`SecretStr` protects sensitive values:

!!! example "docs_src/Settings/AppSettings.php"
    ```php
    public ?SecretStr $api_key = null;
    ```

    ```php
    $settings = AppSettings::load(['APP_API_KEY' => 'super-secret']);

    $settings->api_key->getSecretValue();   // "super-secret"
    (string) $settings->api_key;            // "**********"
    $settings->model_dump()['api_key'];     // "**********"
    $settings->model_dump_json();           // ne contient jamais la valeur réelle
    ```

## Required fields

A field with no default and no environment value raises a `ValidationError`:

!!! example "docs_src/Settings/RequiredSettings.php"
    ```php
    class RequiredSettings extends BaseSettings
    {
        public string $database_url;
    }

    RequiredSettings::load([]);
    // ValidationError : Le champ 'database_url' est requis.
    ```

## Serialization

```php
$settings->model_dump();        // tableau avec les noms de propriétés
$settings->model_dump_json();   // JSON (secrets masqués)
```

Settings have no input aliases of their own: aliases only serve to name environment variables.

## Best practices

- one prefix per application (`APP_`, `MYAPP_`);
- secrets as `SecretStr`, never as `string`;
- a `.env` file **only** for development (it must not be committed);
- tests that inject a simulated environment (second argument) rather than calling `putenv()`.

Next: [Schemas](schemas.md).
