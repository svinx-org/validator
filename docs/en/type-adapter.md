# TypeAdapter

`TypeAdapter` validates **a standalone value**, without a model. It is the equivalent of Pydantic's `TypeAdapter`.

```php
use Svinx\Validator\TypeAdapter;
use Svinx\Validator\Typing\Type;
use Svinx\Validator\Validators\EmailStr;

$adapter = TypeAdapter::of(Type::list(EmailStr::class));

$emails = $adapter->validate(['a@b.co', 'c@d.co']);   // EmailStr[]
$adapter->dump($emails);                              // ['a@b.co', 'c@d.co']
```

## Building an adapter

| Factory | Usage |
| --- | --- |
| `TypeAdapter::of(Type|string $type, array $bindings = [])` | From a `Type`, a class or a native type |
| `TypeAdapter::of(...)->lax()` | Variant with scalar coercion |
| `TypeAdapter::model(string $class)` | Adapter for a complete model |

```php
TypeAdapter::of(EmailStr::class);        // une classe
TypeAdapter::of('int');                  // un type natif
TypeAdapter::of(Type::list(Type::int()));// un type composé
TypeAdapter::model(User::class);         // un modèle
```

`TypeAdapter::model()` rejects a class that is not a model:

```php
TypeAdapter::model(\stdClass::class);   // ✘ n'est pas un modèle de validation
```

## Validating, serializing

| Method | Purpose |
| --- | --- |
| `validate($value)` | Validates and converts |
| `validate_json(string $json)` | Decodes the JSON, then validates (`\JsonException` if the JSON is malformed) |
| `dump($value)` | Converts to a serializable value |
| `dump_json($value, $flags = 0)` | `dump()` + `json_encode` |
| `json_schema()` | JSON schema of the type |
| `json_schema_json($flags = JSON_PRETTY_PRINT)` | Encoded JSON schema |

```php
$adapter = TypeAdapter::of(Type::list(EmailStr::class));

$adapter->validate_json('["a@b.co","c@d.co"]');       // EmailStr[]
$adapter->dump_json($emails);                          // ["a@b.co","c@d.co"]

$adapter->json_schema();
// [
//     'type' => 'array',
//     'items' => ['type' => 'string', 'format' => 'email'],
// ]
```

## With type variables

Bindings are provided at construction time:

```php
use Svinx\Validator\Typing\TypeVar;

$t = TypeVar::of('T');

$adapter = TypeAdapter::of(Type::list($t), ['T' => Type::int()]);
$adapter->validate([1, 2]);     // [1, 2]
$adapter->validate(['a']);      // ✘ Élément 0 ... int attendu
```

## A whole model

```php
use Svinx\Validator\Examples\Models\User;

$adapter = TypeAdapter::model(User::class);

$user = $adapter->validate(['user_id' => 1, 'full_name' => 'Alice']);   // User
$adapter->dump($user);      // tableau avec alias
$adapter->json_schema();    // ['type' => 'object', 'title' => User::class]
```

!!! note "When should you use `TypeAdapter`?"
    - validate a value that is not a model (an identifier, a list of emails, a map of counters);
    - validate at the level of a single function parameter;
    - produce a JSON schema for a standalone value (query string, form field, webhook).

## When to prefer a model

A `TypeAdapter` does not describe several named fields. As soon as several fields are related, write a `BaseModel`: you gain aliases, per-field constraints, validators and `model_dump()`.

```php
// Adapter : une valeur
TypeAdapter::of(Type::map(Type::str(), Type::of(UUID::class)))->validate($data);

// Modèle : plusieurs champs
new Order(['count' => 3, 'owner' => 'a@b.co', 'status' => 'paid']);
```

Next: [Configuration](configuration.md).
