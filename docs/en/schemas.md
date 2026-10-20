# Schemas

A schema describes a model or a type without running any validation. It is used to document an API (OpenAPI), generate a form or verify a contract.

## `model_schema()`

```php
use Svinx\Validator\Examples\Models\User;

User::model_schema();
```

```json
{
    "title": "User",
    "type": "object",
    "properties": {
        "user_id": {
            "type": "integer",
            "description": "ID unique",
            "exclusiveMinimum": 0
        },
        "full_name": {
            "type": "string",
            "minLength": 2,
            "maxLength": 50
        },
        "role": {
            "type": "string",
            "enum": ["admin", "user", "guest"]
        },
        "birth_date": {
            "anyOf": [
                { "type": "object", "title": "DateTime" },
                { "type": "null" }
            ]
        }
    },
    "required": ["user_id"]
}
```

| Key | Content |
| --- | --- |
| `title` | `Config::title` when defined, otherwise the short name of the class |
| `type` | always `"object"` |
| `properties` | one schema per field, indexed by **alias** |
| `required` | fields with no default that are not nullable, indexed by alias |

## Where does a field schema come from?

Each property is converted to a `Typing\Type` by reflection (`Type::fromReflection`), then `Type::schema()` is called. The schema of a field therefore combines:

1. the schema of the type (`integer`, `string` + `format`, `array` + `items`, `anyOf`...);
2. the `#[Field]` constraints;
3. the `#[Field]` metadata (`title`, `description`, `default`).

### Constraint mapping

| `#[Field]` | JSON Schema |
| --- | --- |
| `gt` | `exclusiveMinimum` |
| `ge` | `minimum` |
| `lt` | `exclusiveMaximum` |
| `le` | `maximum` |
| `multiple_of` | `multipleOf` |
| `min_length` | `minLength` |
| `max_length` | `maxLength` |
| `pattern` | `pattern` |
| `choices` | `enum` |
| `title` / `description` / `default` | `title` / `description` / `default` |

### Type mapping

| Type | Schema |
| --- | --- |
| `int` / `float` / `bool` / `string` | `"type": "integer"` / `"number"` / `"boolean"` / `"string"` |
| `array` | `"type": "array"` |
| `Type::list(T)` | `"type": "array"` + `"items"` |
| `Type::map(K, V)` | `"type": "object"` + `"additionalProperties"` |
| `Type::literal(...)` | `"enum": [...]` |
| union / `optional` | `"anyOf": [...]` |
| `null` | `"type": "null"` |
| model (`BaseModel`) | `"type": "object"` + `"title": "<class>"` |
| PHP enumeration | `"type": "string"` + `"title": "<enumeration>"` |
| `\DateTime` and equivalents | `"type": "object"` + `"title"` |
| validated type | `"type": "string"` + `"format"` (see below) |

### Formats of validated types

| Type | `format` |
| --- | --- |
| `EmailStr` | `email` |
| `HttpUrl` | `uri` |
| `IPv4Address` | `ipv4` |
| `IPv6Address` | `ipv6` |
| `UUID` | `uuid` |
| `Date` | `date` |
| `Datetime` | `date-time` |
| `Decimal` | `decimal` |

!!! note "A `format: object` for `\DateTime`"
    The native `\DateTime` type has no standard JSON Schema format that can be detected automatically: its schema is `{"type": "object", "title": "DateTime"}`. To obtain `{"type": "string", "format": "date-time"}`, use the validated type `Datetime` (or `Date`).

## Schema of a standalone type

```php
use Svinx\Validator\Typing\Type;
use Svinx\Validator\Validators\EmailStr;

Type::list(Type::of(EmailStr::class))->schema();
// [
//     'type' => 'array',
//     'items' => ['type' => 'string', 'format' => 'email'],
// ]
```

```php
Type::map(Type::str(), Type::int())->schema();
// ['type' => 'object', 'additionalProperties' => ['type' => 'integer']]
```

```php
use Svinx\Validator\Field;
use Svinx\Validator\Typing\Annotated;

Annotated::of(Type::str(), new Field(min_length: 3, max_length: 5))->schema();
// ['type' => 'string', 'minLength' => 3, 'maxLength' => 5]
```

## Schema of a `TypeAdapter`

```php
use Svinx\Validator\TypeAdapter;

$adapter = TypeAdapter::of(Type::map(Type::str(), Type::of(EmailStr::class)));

$adapter->json_schema();
// [
//     'type' => 'object',
//     'additionalProperties' => ['type' => 'string', 'format' => 'email'],
// ]

$adapter->json_schema_json();   // JSON indenté
```

## Guaranteed properties

- The schema is **deterministic**: same declarations → same array.
- Fields are indexed by **alias** (that is the name exposed to the outside world).
- The order of the properties follows the declaration order in the class.
- `required` never contains a nullable field or a field with a default.

## Current limitations

- no `$defs` / `$ref`: a nested model produces `{"type": "object", "title": "User"}` without exposing its own properties in the parent schema;
- no full definition generation for arrays of models (PHP has no declarative generics);
- no complete OpenAPI output (`components`, `examples`, `discriminator`...).

These limits are deliberate: the schema is a **documentation preview**, not a full OpenAPI generator.

Next: [Errors](errors.md).
