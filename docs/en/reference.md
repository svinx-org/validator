# API Reference

Exhaustive reference of the exposed classes, methods and attributes.

## `Svinx\Validator\BaseModel`

Abstract class that every model inherits from.

### Lifecycle

| Method | Description |
| --- | --- |
| `__construct(array $data = [])` | Validates and assigns the fields |
| `model_validate(array $data): static` | Builds the model (explicit shortcut) |
| `model_validate_json(string $json): static` | Builds from JSON (`\JsonException` if invalid) |

### Serialization

| Method | Description |
| --- | --- |
| `model_dump(?array $include = null, ?array $exclude = null): array` | Serialized array, indexed by alias |
| `model_dump_json(int $flags = 0): string` | JSON |
| `extra(): array` | Unknown keys kept (`extra: allow`) |

### Configuration and schema

| Method | Description |
| --- | --- |
| `model_config(): Config` | Configuration of the model (can be overridden) |
| `model_schema(): array` | JSON schema of the model |

### Fields

A property is a field if it is **public** and **non-static**. Private and protected properties are ignored.

| PHP type | Conversion on input | `model_dump()` output |
| --- | --- | --- |
| `int`, `float`, `bool`, `string`, `array` | strict check | unchanged |
| `?T` | accepts `null` | unchanged |
| `BaseModel` subclass | `array` → instance | recursive array |
| `TypeValidator` subclass | raw → validated instance | `->dump()` |
| `BackedEnum` enumeration | scalar value → case | scalar value |
| `\DateTime` / `\DateTimeImmutable` | string → instance | ISO 8601 string |
| `mixed` / untyped | accepted as is | unchanged |

## `Svinx\Validator\Field`

Attribute targeting a property (`Attribute::TARGET_PROPERTY`).

```php
#[Field(
    alias: null,            // string|null
    title: null,            // string|null
    description: null,      // string|null
    gt: null, ge: null,     // float|int|null
    lt: null, le: null,     // float|int|null
    multiple_of: null,      // float|int|null
    min_length: null,       // int|null
    max_length: null,       // int|null
    pattern: null,          // string|null
    choices: null,          // array|null
    default: null,          // mixed
)]
```

`Field` extends `FieldInfo`, which also exposes:

| Member | Description |
| --- | --- |
| `check(mixed $value, string $name = 'La valeur'): void` | Checks the constraints, raises `ValidationError` |
| `jsonSchema(): array` | Constraints in JSON Schema format |
| `isRequired(): bool` | True without default or factory |
| `getDefault(): mixed` | Default (or the result of the factory) |
| `FieldInfo::make(...)` | Factory equivalent to the constructor |

Inconsistent combinations raise `InvalidArgumentException` at declaration time: `gt` + `ge`, `lt` + `le`, `gt >= lt`, `ge > le`, `min_length > max_length`.

## `Svinx\Validator\FieldValidator`

Attribute targeting a method (`TARGET_METHOD`, repeatable).

```php
#[FieldValidator(
    key: null,                      // string|array|null : nom, alias, liste, '*' ou null
    mode: FieldValidator::AFTER,    // 'after' | 'before'
    always: false,                  // bool : exécuter même si la valeur est null
)]
```

| Constant | Value |
| --- | --- |
| `FieldValidator::BEFORE` | `'before'` |
| `FieldValidator::AFTER` | `'after'` |
| `FieldValidator::ANY` | `'*'` |

Method signature: `public function nom(string $cls, mixed $value): mixed`.

## Validated types (`Svinx\Validator\Validators`)

### `TypeValidator` interface

```php
interface TypeValidator
{
    public static function validate(mixed $raw): static;
    public function value(): mixed;
    public function dump(): mixed;
}
```

### `AbstractType`

Common base: `parse()` (abstract), `value()`, `dump()`, `validate()`, `equals()`, `__toString()`, `jsonSerialize()`.

### Types

| Class | Specific methods |
| --- | --- |
| `EmailStr` | `local()`, `domain()`, `MAX_LENGTH` constant |
| `HttpUrl` | `scheme()`, `host()`, `port()`, `path()`, `query()`, `fragment()`, `SCHEMES` constant |
| `IPv4Address` | `toBinary()`, `toInt()` |
| `IPv6Address` | `toBinary()`, `toIPv4()` |
| `UUID` | `version()`, `variant()`, `toBinary()`, `toCompact()` |
| `Decimal` | `toFloat()`, `toInt()`, `isInteger()`, `isNegative()`, `compare()`, `equals()` |
| `Date` | `dump()`, `toDateTime()`, `year()`, `month()`, `day()` |
| `Datetime` | `dump()`, `toDateTime()`, `toTimezone(string)`, `toDate()` |
| `SecretStr` | `getSecretValue()`, `isEmpty()`, `MASK` constant |

## `Svinx\Validator\Typing`

### `Type`

| Member | Description |
| --- | --- |
| `__construct(string $kind, ...)` | Rarely used directly: prefer the factories |
| `::any/null/bool/int/float/str/array()` | Simple types |
| `::list($item)` → `ListType` | Homogeneous list |
| `::map($key, $value)` → `Map` | Dictionary |
| `::union(...$types)`, `::optional($type)` | Unions |
| `::literal(...$values)` | Closed set of values |
| `::model/validator/enum/object(string)` | By class |
| `::of(string $classe)` | Automatic detection |
| `::from(Type|string|TypeVar)` | Conversion |
| `::fromReflection(?ReflectionType)` | From a PHP declaration |
| `::typevar(TypeVar)` | Type variable |
| `->validate($value, array $bindings = [], ?string $label = null)` | Validation/conversion |
| `->dump($value, array $bindings = [])` | Serialization |
| `->schema(): array` | JSON schema |
| `->matches($value): bool` | Lightweight test |
| `->describe(): string` | Human-readable name |
| `->isNullable(): bool` | Accepts `null` |
| `->withConstraints(FieldInfo ...)`, `->withMetadata(...)` | Enriched copies |
| `->lax()` | Scalar coercion |

Constants: `Type::ANY`, `NULL`, `BOOL`, `INT`, `FLOAT`, `STR`, `ARRAY`, `LIST`, `MAP`, `MODEL`, `VALIDATOR`, `OBJECT`, `ENUM`, `LITERAL`, `UNION`, `TYPEVAR`.

### `ListType`

`ListType::make(Type|string|TypeVar $item)`, `item(): Type`.

### `Map`

`Map::make($key, $value)`, `keyType(): Type`, `valueType(): Type`.

### `TypeVar`

`TypeVar::of(string $name, Type|string|null $bound = null, Type|string|null $default = null)`, `describe()`.

### `Annotated`

`Annotated::of(Type|string|TypeVar $type, mixed ...$metadata): Type` — `FieldInfo` objects become constraints.

## `Svinx\Validator\TypeAdapter`

Final class.

| Member | Description |
| --- | --- |
| `::of(Type|string|TypeVar $type, array $bindings = [])` | Builds the adapter |
| `::model(string $class)` | Adapter for a model |
| `->lax()` | Lax variant |
| `->validate($value)` | Validates and converts |
| `->validate_json(string $json)` | From JSON |
| `->dump($value)` | Serializes |
| `->dump_json($value, int $flags = 0)` | JSON |
| `->json_schema()` | JSON schema |
| `->json_schema_json(int $flags = JSON_PRETTY_PRINT)` | Encoded JSON schema |
| `->type`, `->bindings` | Read-only properties |

## `Svinx\Validator\Config`

| Member | Description |
| --- | --- |
| `__construct(extra, populate_by_name, alias_generator, str_strip_whitespace, str_to_lower, str_to_upper, title)` | Options |
| `::make(array $options)` | Factory with partial overrides |
| `->with(array $changes)` | Modified copy |
| `->toArray()` | All the options |
| `->aliasFor(string $property, ?string $alias)` | Effective alias |
| `->normalizeString(mixed $value)` | Normalization of a string |

Constants: `Config::EXTRA_IGNORE`, `Config::EXTRA_FORBID`, `Config::EXTRA_ALLOW`.

## `Svinx\Validator\Settings\BaseSettings`

Abstract class extending `BaseModel`.

| Member | Description |
| --- | --- |
| `__construct(?array $data = null, ?array $env = null)` | Explicit data + simulated environment |
| `::load(?array $env = null): static` | Loading from the environment |
| `::env_prefix(): string` | Prefix of the variables |
| `::env_file(): ?string` | Path of a `.env` file |
| `::env_nested_delimiter(): ?string` | Delimiter for nested settings |
| `::case_sensitive(): bool` | Case sensitivity of the names |
| `::readEnvironment(?string $file = null): array` | `.env` + variables réelles |
| `::parseEnvFile(string $file): array` | Parsing of a `.env` file |

The field types are validated in lax mode.

## `Svinx\Validator\ValidationError`

Extends `\InvalidArgumentException`. Every data validation error (and declaration error) goes through this exception.

## Package file tree

```
src/
├── BaseModel.php
├── Config.php
├── Field.php
├── FieldInfo.php
├── FieldValidator.php
├── ModelValidator.php
├── TypeAdapter.php
├── ValidationError.php
├── Settings/
│   └── BaseSettings.php
├── Typing/
│   ├── Annotated.php
│   ├── ListType.php
│   ├── Map.php
│   ├── Type.php
│   └── TypeVar.php
└── Validators/
    ├── AbstractType.php
    ├── TypeValidator.php
    ├── Date.php
    ├── Datetime.php
    ├── Decimal.php
    ├── EmailStr.php
    ├── HttpUrl.php
    ├── IPv4Address.php
    ├── IPv6Address.php
    ├── SecretStr.php
    └── UUID.php
```

The documented examples live in `docs_src/` (namespace `Svinx\Validator\Examples`), the tests in `tests/`.
