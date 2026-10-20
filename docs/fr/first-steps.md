# Premiers pas

## Déclarer un modèle

Un modèle est une classe qui étend `BaseModel`. Chaque **propriété publique typée** devient un champ validé.

!!! example "docs_src/Models/User.php"
    ```php
    <?php

    namespace Svinx\Validator\Examples\Models;

    use Svinx\Validator\BaseModel;
    use Svinx\Validator\Field;

    class User extends BaseModel
    {
        #[Field(alias: 'user_id', description: 'ID unique', gt: 0)]
        public int $id;

        #[Field(alias: 'full_name', min_length: 2, max_length: 50)]
        public string $name = 'Anonyme';

        #[Field(choices: ['admin', 'user', 'guest'])]
        public string $role = 'user';

        #[Field(alias: 'birth_date')]
        public ?\DateTime $birthday = null;
    }
    ```

Trois informations s'y trouvent :

| Élément | Signification |
| --- | --- |
| `public int $id` | Le type PHP est la **règle de type** (un `string` sera refusé) |
| `#[Field(alias: 'user_id')]` | La clé attendue dans les données d'entrée |
| `= 'user'` | La valeur par défaut si la clé est absente |

## Construire un modèle

```php
use Svinx\Validator\Examples\Models\User;

$user = new User([
    'user_id' => 42,
    'full_name' => 'Jean Dupont',
    'role' => 'admin',
]);

$user->id;        // 42
$user->name;      // "Jean Dupont"
$user->role;      // "admin"
$user->birthday;  // null (type optionnel, clé absente)
```

Les clés inconnues sont **ignorées** par défaut (voir [Configuration](configuration.md) pour les interdire).

## Que se passe-t-il quand une valeur est invalide ?

La construction s'arrête sur la **première** erreur et lève une `ValidationError` :

```php
new User(['user_id' => 0, 'full_name' => 'Jean']);
// Svinx\Validator\ValidationError : Le champ 'id' doit être > 0
```

```php
new User(['user_id' => '42']);
// Svinx\Validator\ValidationError : Le champ 'id' : int attendu, string donné ('42').
```

```php
new User([]);
// Svinx\Validator\ValidationError : Le champ 'id' est requis.
```

!!! note "Pas de coercition implicite"
    `'42'` n'est pas converti en `42`. La coercition existe (`Type::lax()`), mais uniquement là où vous la demandez explicitement — par exemple pour les [Settings](settings.md). Un modèle « strict » ne devine pas vos intentions.

`ValidationError` étend `\InvalidArgumentException`, vous pouvez donc l'attraper sans dépendre du namespace :

```php
try {
    new User(['user_id' => 0]);
} catch (\InvalidArgumentException $e) {
    echo $e->getMessage(); // Le champ 'id' doit être > 0
}
```

## Sérialiser un modèle : `model_dump()`

`model_dump()` renvoie un tableau prêt pour `json_encode`, avec les **alias** :

```php
$user = new User(['user_id' => 42, 'full_name' => 'Jean Dupont']);

$user->model_dump();
// [
//     'user_id'   => 42,
//     'full_name' => 'Jean Dupont',
//     'role'      => 'user',
//     'birth_date'=> null,
// ]
```

Inclusions et exclusions :

```php
$user->model_dump(include: ['name', 'role']);   // uniquement ces champs
$user->model_dump(exclude: ['birthday']);       // tout sauf celui-ci
```

!!! warning "`include` et `exclude` attendent des **noms de propriété**, pas des alias."

`model_dump_json()` produit directement du JSON :

```php
echo $user->model_dump_json();            // {"user_id":42,...}
echo $user->model_dump_json(JSON_PRETTY_PRINT);
```

Les objets sont convertis en valeurs JSON simples :

| Type de propriété | Valeur dans `model_dump()` |
| --- | --- |
| modèles imbriqués | tableau associatif (`model_dump()` récursif) |
| `EmailStr`, `UUID`, `Decimal`... | la valeur normalisée (`string`) |
| `\DateTime` / `\DateTimeImmutable` | chaîne ISO 8601 (`1990-05-12T10:00:00+00:00`) |
| énumérations PHP `BackedEnum` | la valeur scalaire de l'énumération |
| `SecretStr` | `**********` |

## Reconstruire depuis un tableau ou du JSON

`model_validate()` et `model_validate_json()` sont des raccourcis explicites :

```php
$user = User::model_validate(['user_id' => 1, 'full_name' => 'Bob']);
$user = User::model_validate_json('{"user_id":1,"full_name":"Bob"}');
```

## Décrire un modèle : `model_schema()`

```php
User::model_schema();
```

```json
{
    "title": "User",
    "type": "object",
    "properties": {
        "user_id":  { "type": "integer", "description": "ID unique", "exclusiveMinimum": 0 },
        "full_name": { "type": "string", "minLength": 2, "maxLength": 50 },
        "role":      { "type": "string", "enum": ["admin", "user", "guest"] },
        "birth_date": { "anyOf": [ { "type": "object", "title": "DateTime" }, { "type": "null" } ] }
    },
    "required": ["user_id"]
}
```

C'est un sous-ensemble de JSON Schema, suffisant pour générer une documentation OpenAPI. Les détails sont dans [Schémas](schemas.md).

## Récapitulatif

```php
new User($data);                     // construire
User::model_validate($data);         // idem, explicite
User::model_validate_json($json);    // depuis du JSON
$user->model_dump();                 // tableau avec alias
$user->model_dump_json();            // JSON
User::model_schema();                // JSON Schema
```

La suite : [les contraintes `#[Field]`](fields-and-constraints.md).
