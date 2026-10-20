# Configuration

`Config` regroupe les options d'un modèle — l'équivalent de `model_config = ConfigDict(...)` de Pydantic.

## Déclarer une configuration

Une classe modèle remplace `model_config()` par la configuration voulue :

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

## Les options

| Option                 | Défaut         | Effet                                                       |
| ---------------------- | -------------- | ----------------------------------------------------------- |
| `extra`                | `EXTRA_IGNORE` | Que faire des clés inconnues : `ignore`, `forbid`, `allow`  |
| `populate_by_name`     | `true`         | Accepter le nom de propriété en plus de l'alias             |
| `alias_generator`      | `null`         | `callable(string $property): string` pour générer les alias |
| `str_strip_whitespace` | `false`        | Retirer les espaces autour des chaînes                      |
| `str_to_lower`         | `false`        | Forcer la casse minuscule des chaînes                       |
| `str_to_upper`         | `false`        | Forcer la casse majuscule des chaînes                       |
| `title`                | `null`         | Titre du modèle utilisé par `model_schema()`                |

!!! note "Différence avec Pydantic"
    Pydantic v2 laisse `populate_by_name` à `false` (l'alias est obligatoire). Ici il vaut `true` par défaut : accepter `'id'` **et** `'user_id'` est plus indulgent pour les API existantes. Passez-le à `false` pour un comportement strictement équivalent à Pydantic.

### `extra`

| Valeur                 | Comportement                                                         |
| ---------------------- | -------------------------------------------------------------------- |
| `Config::EXTRA_IGNORE` | Les clés inconnues sont ignorées (défaut)                            |
| `Config::EXTRA_FORBID` | `ValidationError` listant les clés inconnues                         |
| `Config::EXTRA_ALLOW`  | Les clés inconnues sont conservées et ressortent dans `model_dump()` |

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

Une valeur `extra` inconnue lève une `InvalidArgumentException` à la construction de la configuration :

```php
new Config(extra: 'bogus');
// InvalidArgumentException : Option extra inconnue : "bogus" (attendu : ignore, forbid ou allow).
```

### `populate_by_name`

Avec `false`, seul l'alias est accepté en entrée :

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

Génère les alias des propriétés qui n'en déclarent pas :

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

Un alias déclaré dans `#[Field(alias: ...)]` reste **prioritaire** sur le générateur.

### Normalisation des chaînes

Appliquée à la valeur brute, **avant** les validateurs, et uniquement aux chaînes :

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

Les valeurs par défaut ne sont pas normalisées : elles sont écrites par le développeur, pas par l'appelant.

## Construire des configurations

```php
use Svinx\Validator\Config;

$config = Config::make(['extra' => Config::EXTRA_FORBID, 'title' => 'Utilisateur']);

$config->extra;    // "forbid"
$config->title;    // "Utilisateur"
```

`make()` refuse les options inconnues :

```php
Config::make(['inconnu' => true]);
// InvalidArgumentException : Options de configuration inconnues : inconnu
```

Copier une configuration en changeant une option :

```php
$strict = $config->with(['extra' => Config::EXTRA_FORBID]);

$config->toArray();   // toutes les options
```

## Utiliser une configuration directement

`Config` n'est pas réservé à `BaseModel` :

```php
$config = new Config(populate_by_name: false);

$config->aliasFor('first_name', null);        // null
(new Config(alias_generator: 'strtoupper'))->aliasFor('name', null);   // "NAME"
(new Config(alias_generator: 'strtoupper'))->aliasFor('name', 'nom');  // "nom" (priorité au Field)

$config->normalizeString('  abc  ');          // inchangé si aucune option de normalisation
```

`alias_generator` doit être *callable* ; sinon :

```php
new Config(alias_generator: 'pas une fonction');
// InvalidArgumentException : alias_generator doit être callable ou null.
```

## Erreurs de configuration

| Cas                            | Exception                  |
| ------------------------------ | -------------------------- |
| `extra` inconnu                | `InvalidArgumentException` |
| `alias_generator` non callable | `InvalidArgumentException` |
| option inconnue dans `make()`  | `InvalidArgumentException` |

Les erreurs de configuration sont des **erreurs de programmation** : elles survolent dès la lecture de la configuration, avant toute donnée utilisateur.

La suite : [Settings](settings.md).
