# Configuration

`Config` groups the options of a model — the equivalent of Pydantic's `model_config = ConfigDict(...)`.

## Declaring a configuration

A model class overrides `model_config()` with the configuration it wants:

!!! example "docs_src/Config/ForbidConfigModel.php"
    ```php
    namespace Svinx\Validator\Examples\Config;

    use Svinx\Validator\BaseModel;
    use Svinx\Validator\Config;

    class ForbidConfigModel extends BaseModel
    {
        public static function model_config(): Config
        {
            return new Config(extra: Config::EXTRA_FORBID);
        }

        public string $name = '';
    }
    ```

    ```php
    new ForbidConfigModel(['name' => 'ok', 'inconnu' => 'x']);
    // ValidationError : Champs inconnus : inconnu.
    ```

## The options

| Option                 | Default         | Effect                                                       |
| ---------------------- | --------------- | ----------------------------------------------------------- |
| `extra`                | `EXTRA_IGNORE` | What to do with unknown keys: `ignore`, `forbid`, `allow`    |
| `populate_by_name`     | `true`         | Accept the property name in addition to the alias             |
| `alias_generator`      | `null`         | `callable(string $property): string` to generate the aliases  |
| `str_strip_whitespace` | `false`        | Strip whitespace around strings                              |
| `str_to_lower`         | `false`        | Force strings to lower case                                  |
| `str_to_upper`         | `false`        | Force strings to upper case                                  |
| `title`                | `null`         | Model title used by `model_schema()`                          |

!!! note "Difference with Pydantic"
    Pydantic v2 leaves `populate_by_name` at `false` (the alias is mandatory). Here it defaults to `true`: accepting `'id'` **and** `'user_id'` is more forgiving for existing APIs. Set it to `false` for behaviour strictly equivalent to Pydantic.

### `extra`

| Value                 | Behaviour                                                           |
| --------------------- | ------------------------------------------------------------------- |
| `Config::EXTRA_IGNORE` | Unknown keys are ignored (default)                                 |
| `Config::EXTRA_FORBID` | `ValidationError` listing the unknown keys                          |
| `Config::EXTRA_ALLOW`  | Unknown keys are kept and show up again in `model_dump()`           |

!!! example "docs_src/Config/AllowConfigModel.php"
    ```php
    class AllowConfigModel extends BaseModel
    {
        public static function model_config(): Config
        {
            return new Config(extra: Config::EXTRA_ALLOW);
        }

        public string $name = '';
    }

    $model = new AllowConfigModel(['name' => 'ok', 'surplus' => 42]);

    $model->extra();        // ['surplus' => 42]
    $model->model_dump();   // ['name' => 'ok', 'surplus' => 42]
    ```

An unknown `extra` value raises an `InvalidArgumentException` when the configuration is constructed:

```php
new Config(extra: 'bogus');
// InvalidArgumentException : Option extra inconnue : "bogus" (attendu : ignore, forbid ou allow).
```

### `populate_by_name`

With `false`, only the alias is accepted on input:

!!! example "docs_src/Config/NoPopulateByNameModel.php"
    ```php
    class NoPopulateByNameModel extends BaseModel
    {
        public static function model_config(): Config
        {
            return new Config(populate_by_name: false);
        }

        #[Field(alias: 'n')]
        public string $name;
    }

    new NoPopulateByNameModel(['n' => 'ok']);      // ✔
    new NoPopulateByNameModel(['name' => 'ok']);   // ✘ Le champ 'name' est requis.
    ```

### `alias_generator`

Generates the aliases of properties that do not declare one:

!!! example "docs_src/Config/AliasGeneratorModel.php"
    ```php
    class AliasGeneratorModel extends BaseModel
    {
        public static function model_config(): Config
        {
            return new Config(alias_generator: static fn (string $property) => 'X_' . strtoupper($property));
        }

        public string $first_name = '';
    }

    $model = new AliasGeneratorModel(['X_FIRST_NAME' => 'Alice']);

    $model->first_name;      // "Alice"
    $model->model_dump();    // ['X_FIRST_NAME' => 'Alice']
    ```

An alias declared in `#[Field(alias: ...)]` always takes **precedence** over the generator.

### String normalization

Applied to the raw value, **before** the validators, and only to strings:

!!! example "docs_src/Config/NormalizedConfigModel.php"
    ```php
    class NormalizedConfigModel extends BaseModel
    {
        public static function model_config(): Config
        {
            return new Config(str_strip_whitespace: true, str_to_upper: true);
        }

        public string $code = '';
        public int $number = 0;
    }

    (new NormalizedConfigModel(['code' => '  abc  ']))->code;   // "ABC"
    (new NormalizedConfigModel(['number' => 12]))->number;      // 12 (inchangé)
    ```

Default values are not normalized: they are written by the developer, not by the caller.

## Building configurations

```php
use Svinx\Validator\Config;

$config = Config::make(['extra' => Config::EXTRA_FORBID, 'title' => 'Utilisateur']);

$config->extra;    // "forbid"
$config->title;    // "Utilisateur"
```

`make()` rejects unknown options:

```php
Config::make(['inconnu' => true]);
// InvalidArgumentException : Options de configuration inconnues : inconnu
```

Copy a configuration while changing one option:

```php
$strict = $config->with(['extra' => Config::EXTRA_FORBID]);

$config->toArray();   // toutes les options
```

## Using a configuration directly

`Config` is not reserved for `BaseModel`:

```php
$config = new Config(populate_by_name: false);

$config->aliasFor('first_name', null);        // null
(new Config(alias_generator: 'strtoupper'))->aliasFor('name', null);   // "NAME"
(new Config(alias_generator: 'strtoupper'))->aliasFor('name', 'nom');  // "nom" (priorité au Field)

$config->normalizeString('  abc  ');          // inchangé si aucune option de normalisation
```

`alias_generator` must be *callable*; otherwise:

```php
new Config(alias_generator: 'pas une fonction');
// InvalidArgumentException : alias_generator doit être callable ou null.
```

## Configuration errors

| Case                            | Exception                  |
| ------------------------------- | -------------------------- |
| unknown `extra`                 | `InvalidArgumentException` |
| non-callable `alias_generator`  | `InvalidArgumentException` |
| unknown option in `make()`      | `InvalidArgumentException` |

Configuration errors are **programming errors**: they surface as soon as the configuration is read, before any user data.

Next: [Settings](settings.md).
