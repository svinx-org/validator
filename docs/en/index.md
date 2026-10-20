# Svinx Validator

**Pydantic-style model and schema validation, in pure PHP.**

`Svinx\Validator` brings to PHP 8.4 the ideas that make Pydantic a pleasure to use:

- you declare **typed properties**;
- the library **validates, converts and serializes** raw data (`json_decode` arrays, environment variables, forms...);
- you add your **business rules** with methods, not with nested configuration arrays.

```php
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

class User extends BaseModel
{
    #[Field(alias: 'user_id', gt: 0)]
    public int $id;

    #[Field(alias: 'full_name', min_length: 2)]
    public string $name = 'Anonyme';
}

$user = new User(['user_id' => 42, 'full_name' => 'Jean']);

$user->id;                    // int(42)
$user->name;                  // "Jean"
$user->model_dump();          // ['user_id' => 42, 'full_name' => 'Jean']
```

An invalid value never yields a half-filled model: a `ValidationError` is raised **before** the object is constructed.

```php
new User(['user_id' => 0]);   // Svinx\Validator\ValidationError : Le champ 'id' doit être > 0
```

---

## What the library covers

| Building block | Role | Chapter |
| --- | --- | --- |
| `BaseModel` | Builds a validated model from an array | [First steps](first-steps.md) |
| `Field` | Declarative constraints (`gt`, `min_length`, `choices`, aliases...) | [Fields and constraints](fields-and-constraints.md) |
| Nested models | Models inside models, arrays of models | [Nested models](nested-models.md) |
| Validated types | `EmailStr`, `HttpUrl`, `UUID`, `Decimal`, `Date`... | [Validated types](validated-types.md) |
| `FieldValidator` | Custom validators (the equivalent of `@field_validator`) | [Custom validators](custom-validators.md) |
| `Typing\Type` | Type descriptors (`list[int]`, `Optional`, `Literal`, `TypeVar`...) | [Types and annotations](typing.md) |
| `TypeAdapter` | Validate a single value, without a model | [TypeAdapter](type-adapter.md) |
| `Config` | Model options (`extra`, automatic aliases, normalization) | [Configuration](configuration.md) |
| `BaseSettings` | Typed configuration from the environment and `.env` | [Settings](settings.md) |
| Schemas | `model_schema()`, JSON Schema, OpenAPI formats | [Schemas](schemas.md) |
| `ValidationError` | Understanding and handling errors | [Errors](errors.md) |

---

## The course

1. [Installation](installation.md)
2. [First steps](first-steps.md)
3. [Fields and constraints](fields-and-constraints.md)
4. [Nested models](nested-models.md)
5. [Validated types](validated-types.md)
6. [Custom validators](custom-validators.md)
7. [Types and annotations](typing.md)
8. [TypeAdapter](type-adapter.md)
9. [Configuration](configuration.md)
10. [Settings](settings.md)
11. [Schemas](schemas.md)
12. [Errors](errors.md)
13. [API reference](reference.md)

---

## Who this course is for

Anyone writing server-side PHP and handling untrusted incoming data: HTTP APIs, command lines, message-queue consumers, configuration files.

The course assumes you know the basics of PHP (classes, types, attributes). No prior knowledge of Pydantic is required: analogies are pointed out where they exist, never assumed.

!!! note "Deliberate differences from Pydantic"
    PHP has no generics, no `|` operator for parameterized types and no decorators. Compound types are therefore described by objects (`Type::list(Type::int())`) and validators by PHP 8 attributes (`#[Field(...)]`, `#[FieldValidator(...)]`). Every difference is documented as you go.
