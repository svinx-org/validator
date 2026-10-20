# First steps

## Declaring a model

A model is a class that extends `BaseModel`. Every **typed public property** becomes a validated field.

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

Three pieces of information are at work here:

| Element | Meaning |
| --- | --- |
| `public int $id` | The PHP type is the **type rule** (a `string` is rejected) |
| `#[Field(alias: 'user_id')]` | The key expected in the input data |
| `= 'user'` | The default value when the key is missing |

## Building a model

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

Unknown keys are **ignored** by default (see [Configuration](configuration.md) to forbid them).

## What happens when a value is invalid?

Construction stops at the **first** error and raises a `ValidationError`:

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

!!! note "No implicit coercion"
    `'42'` is not converted to `42`. Coercion exists (`Type::lax()`), but only where you ask for it explicitly — for example in [Settings](settings.md). A "strict" model does not guess your intentions.

`ValidationError` extends `\InvalidArgumentException`, so you can catch it without depending on the namespace:

```php
try {
    new User(['user_id' => 0]);
} catch (\InvalidArgumentException $e) {
    echo $e->getMessage(); // Le champ 'id' doit être > 0
}
```

## Serializing a model: `model_dump()`

`model_dump()` returns an array ready for `json_encode`, using the **aliases**:

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

Inclusion and exclusion:

```php
$user->model_dump(include: ['name', 'role']);   // uniquement ces champs
$user->model_dump(exclude: ['birthday']);       // tout sauf celui-ci
```

!!! warning "`include` and `exclude` expect **property names**, not aliases."

`model_dump_json()` produces JSON directly:

```php
echo $user->model_dump_json();            // {"user_id":42,...}
echo $user->model_dump_json(JSON_PRETTY_PRINT);
```

Objects are converted to plain JSON values:

| Property type | Value in `model_dump()` |
| --- | --- |
| nested models | associative array (recursive `model_dump()`) |
| `EmailStr`, `UUID`, `Decimal`... | the normalized value (`string`) |
| `\DateTime` / `\DateTimeImmutable` | ISO 8601 string (`1990-05-12T10:00:00+00:00`) |
| PHP `BackedEnum` enumerations | the scalar value of the enumeration |
| `SecretStr` | `**********` |

## Rebuilding from an array or from JSON

`model_validate()` and `model_validate_json()` are explicit shortcuts:

```php
$user = User::model_validate(['user_id' => 1, 'full_name' => 'Bob']);
$user = User::model_validate_json('{"user_id":1,"full_name":"Bob"}');
```

## Describing a model: `model_schema()`

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

This is a subset of JSON Schema, enough to generate OpenAPI documentation. The details are in [Schemas](schemas.md).

## Summary

```php
new User($data);                     // construire
User::model_validate($data);         // idem, explicite
User::model_validate_json($json);    // depuis du JSON
$user->model_dump();                 // tableau avec alias
$user->model_dump_json();            // JSON
User::model_schema();                // JSON Schema
```

Next: [`#[Field]` constraints](fields-and-constraints.md).
