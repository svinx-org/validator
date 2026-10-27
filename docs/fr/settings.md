# Settings

`BaseSettings` lit la configuration de l'application depuis l'**environnement** (variables d'environnement et fichier `.env`) en la validant avec les mêmes types et contraintes qu'un modèle.

## Déclarer des settings

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

## La priorité des sources

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

`load()` est un raccourci : `AppSettings::load($env)` ≡ `new AppSettings(null, $env)`.

!!! tip "Tester sans toucher à l'environnement"
    Le second argument du constructeur (et de `load()`) est un environnement **simulé** : aucun `putenv()` n'est nécessaire dans les tests.

## Nom des variables

Par défaut : `env_prefix()` + nom de propriété en `MAJUSCULES_SNAKE_CASE`.

| Propriété | Variable |
| --- | --- |
| `$env` | `APP_ENV` |
| `$admin_email` | `APP_ADMIN_EMAIL` |
| `$port` | `APP_PORT` |

Un `#[Field(alias: ...)]` fournit un nom alternatif :

```php
#[Field(alias: 'jwt_secret')]
public ?string $token;      // lu dans APP_JWT_SECRET
```

Le nom de propriété est toujours accepté en second recours (`APP_TOKEN`).

!!! note "Sans préfixe, l'environnement système est ignoré"
    `ENV` et `PORT` ne sont pas lus par `AppSettings` (préfixe `APP_`) : cela évite les collisions avec l'environnement du système (`PORT`, `DEBUG`...). Les settings sans préfixe existent, mais exposez plutôt un préfixe.

### Sensibilité à la casse

Par défaut, la casse est **ignorée** : `APP_ENV` et `app_env` désignent le même champ.

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

## Settings imbriqués

Avec `env_nested_delimiter()`, un champ dont le type est un autre `BaseSettings` est construit depuis les variables préfixées :

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

Les valeurs passées explicitement restent prioritaires et se fusionnent avec l'environnement :

```php
new AppSettings(['db' => ['host' => 'explicit']])->db->host;   // "explicit"
```

## Fichier `.env`

`env_file()` renvoie le chemin du fichier à charger :

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

Le format supporté est volontairement minimal :

| Syntaxe | Supportée |
| --- | --- |
| `CLE=valeur` | ✔ |
| `export CLE=valeur` | ✔ |
| commentaires `#` et lignes vides | ✔ |
| valeurs entre guillemets (`"..."` ou `'...'`) | ✔ (échappements interprétés dans les guillemets doubles) |
| interpolation de variables, blocs multi-lignes | ✘ |

!!! warning "Priorité des sources"
    Les variables d'environnement **réelles** sont prioritaires sur le fichier `.env`. C'est le comportement attendu (un fichier `.env` est un défaut de développement), et c'est vérifié par les tests.

Le fichier peut aussi être analysé directement :

```php
BaseSettings::parseEnvFile('/chemin/vers/.env');   // array<string, string>
BaseSettings::readEnvironment('/chemin/vers/.env'); // .env + variables réelles
```

## La coercition des types

Les variables d'environnement sont **toujours des chaînes**. Les settings valident donc en mode laxiste (`Type::lax()`) :

| Variable | Propriété | Résultat |
| --- | --- | --- |
| `APP_PORT=9000` | `int $port` | `9000` |
| `APP_DEBUG=true` | `bool $debug` | `true` (`1`, `yes`, `on` acceptés ; `0`, `false`, `no`, `off` aussi) |
| `APP_RATIO=0.5` | `float $ratio` | `0.5` |
| `APP_PORT=abc` | `int $port` | ✘ `ValidationError` |

Un modèle classique (`BaseModel`) **ne coerce pas** : la coercition est propre aux settings, ou demandée explicitement via `Type::lax()`.

## Secrets

`SecretStr` protège les valeurs sensibles :

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

## Champs requis

Un propre champ sans défaut ni valeur d'environnement lève une `ValidationError` :

!!! example "docs_src/Settings/RequiredSettings.php"
```php
    class RequiredSettings extends BaseSettings
    {
        public string $database_url;
    }

    RequiredSettings::load([]);
    // ValidationError : Le champ 'database_url' est requis.
```

## Sérialisation

```php
$settings->model_dump();        // tableau avec les noms de propriétés
$settings->model_dump_json();   // JSON (secrets masqués)
```

Les settings n'ont pas d'alias d'entrée propres : les alias servent uniquement à nommer les variables d'environnement.

## Bonnes pratiques

- un préfixe par application (`APP_`, `MYAPP_`) ;
- les secrets en `SecretStr`, jamais en `string` ;
- un fichier `.env` **seulement** pour le développement (il ne doit pas être commité) ;
- des tests qui injectent un environnement simulé (second argument) plutôt que d'appeler `putenv()`.

La suite : [Schémas](schemas.md).
