# Types and annotations

PHP has no generics: you cannot write `list<int>` in a property declaration. `Svinx\Validator\Typing` fills that gap with **objects that describe a type**.

```php
use Svinx\Validator\Typing\Type;

Type::int();                              // int
Type::list(Type::int());                  // list[int]
Type::map(Type::str(), Type::int());      // map[string, int]
Type::optional(Type::of(EmailStr::class));// EmailStr | null
Type::literal('a', 'b');                  // literal('a', 'b')
```

A `Type` knows how to:

| Method | Purpose |
| --- | --- |
| `validate($value, $bindings = [], $label = null)` | Validate and convert a raw value |
| `dump($value, $bindings = [])` | Convert a validated value into a serializable value |
| `schema()` | Return the JSON schema of the type |
| `matches($value)` | Lightweight check, without conversion |
| `describe()` | Human-readable name (`list[int]`, `int \| null`...) |
| `isNullable()` | True if `null` is accepted |
| `withConstraints(FieldInfo ...)` | Add constraints (see `Annotated`) |
| `lax()` | Enable scalar coercion |

## Factories

| Factory | Produced type | `describe()` |
| --- | --- | --- |
| `Type::any()` | anything | `mixed` |
| `Type::null()` | `null` | `null` |
| `Type::bool()` | boolean | `bool` |
| `Type::int()` | integer | `int` |
| `Type::float()` | number | `float` |
| `Type::str()` | string | `string` |
| `Type::array()` | free-form array | `array` |
| `Type::list($item)` | homogeneous list | `list[int]` |
| `Type::map($key, $value)` | dictionary | `map[string, int]` |
| `Type::union(...$types)` | union | `int \| string` |
| `Type::optional($type)` | `$type` or `null` | `int \| null` |
| `Type::literal(...$valeurs)` | closed set of values | `literal('a', 'b')` |
| `Type::of($classe)` | depending on the class | `EmailStr`, `User`, ... |
| `Type::model($classe)` | validation model | `User` |
| `Type::validator($classe)` | validated type | `EmailStr` |
| `Type::enum($enum)` | PHP enumeration | `OrderStatus` |
| `Type::object($classe)` | any other object | `DateTime` |
| `Type::typevar($var)` | type variable | `$T` |

`Type::of()` automatically picks the right factory:

```php
Type::of(EmailStr::class);    // VALIDATOR
Type::of(OrderStatus::class); // ENUM
Type::of(User::class);        // MODEL
Type::of(DateTime::class);    // OBJECT
Type::of('int');              // INT (les types natifs sont aussi acceptés)
```

The `kind` constants are exposed: `Type::INT`, `Type::LIST`, `Type::UNION`...

## Validating

```php
use Svinx\Validator\Typing\Type;

Type::int()->validate(42);            // 42
Type::int()->validate('42');          // ✘ ValidationError : Valeur : int attendu, string donné ('42').

Type::int()->lax()->validate('42');   // 42  (coercition explicite)
Type::int()->lax()->validate('4.5');  // ✘
```

Scalars are **strict** by default. `lax()` is the only way to get coercion, and it is propagated to the members:

```php
$type = Type::map(Type::str(), Type::int())->lax();

$type->validate(['page' => '2']);     // ['page' => 2]
```

### Lists and dictionaries

```php
$list = Type::list(Type::int());
$list->validate([1, 2, 3]);           // [1, 2, 3]
$list->validate(['a' => 1]);          // ✘ : une liste doit être indexée 0, 1, 2...
$list->validate([1, 'deux']);         // ✘ Élément 1 de la valeur : int attendu...
```

```php
$map = Type::map(Type::str(), Type::int())->lax();
$map->validate(['age' => '42', 7 => '8']);   // ['age' => 42, '7' => 8]
```

!!! note "Array keys"
    PHP converts numeric keys to `int`. `Type::map(Type::str(), …)` turns the keys back into strings so validation does not fail on `'7'`.

### Unions and null values

```php
$type = Type::union(Type::int(), Type::str());

$type->validate(1);        // 1
$type->validate('abc');    // "abc"
$type->validate(1.5);      // ✘ aucune branche ne correspond
```

`Type::optional(Type::int())` is a shortcut for `Type::union(Type::int(), Type::null())`:

```php
Type::optional(Type::int())->validate(null);   // null
```

### Closed sets of values and enumerations

```php
Type::literal('a', 'b')->validate('a');   // "a"

use Svinx\Validator\Examples\Typing\OrderStatus;

Type::of(OrderStatus::class)->validate('paid');   // OrderStatus::Paid
```

### Validation models

```php
use Svinx\Validator\Examples\Models\User;

$type = Type::of(User::class);

$user = $type->validate(['user_id' => 1, 'full_name' => 'Alice']);  // User
$type->validate($user);                                             // même instance
```

## Hand-built types: `ListType` and `Map`

The `ListType` and `Map` classes are the underlying objects; they are useful for inspecting a type:

```php
use Svinx\Validator\Typing\ListType;
use Svinx\Validator\Typing\Map;

$list = ListType::make(Type::int());
$list->item();          // Type(int)

$map = Map::make(Type::str(), Type::int());
$map->keyType();        // Type(string)
$map->valueType();      // Type(int)
```

## `TypeVar`: type variables

`TypeVar` describes a type that is unknown at write time and resolved later through *bindings*:

```php
use Svinx\Validator\Typing\TypeVar;

$t = TypeVar::of('T');

$type = Type::list($t);
$type->validate([1, 2], ['T' => Type::int()]);    // [1, 2]
$type->validate(['a'], ['T' => Type::str()]);     // ["a"]
$type->validate([1]);                             // ✘ Variable de type non liée : T.
```

Bounds and defaults:

```php
$bound   = TypeVar::of('T', Type::int());       // borne : int
$default = TypeVar::of('U', null, Type::str()); // défaut : string
```

A `lax()` container propagates coercion to the type variables bound inside it:

```php
Type::list($t)->lax()->validate(['1'], ['T' => Type::int()]);   // [1]
```

## `Annotated`: a type with constraints

`Annotated::of()` attaches `FieldInfo` objects (constraints) and free-form metadata to a type:

```php
use Svinx\Validator\Field;
use Svinx\Validator\Typing\Annotated;

$type = Annotated::of(Type::str(), new Field(min_length: 3, max_length: 5));

$type->validate('abcd');    // "abcd"
$type->validate('ab');      // ✘ doit avoir au moins 3 caractères
$type->schema();            // ['type' => 'string', 'minLength' => 3, 'maxLength' => 5]
```

`FieldInfo` objects become constraints; any other value becomes metadata:

```php
$type = Annotated::of(Type::int(), 'identifiant métier', 42);

$type->metadata;   // ['identifiant métier', 42]
```

!!! note "No `pydantic.Field` inside PHP generics"
    Pydantic writes `Annotated[int, Field(gt=0)]`; here it is an object built at runtime: `Annotated::of(Type::int(), new Field(gt: 0))`.

## From reflection

`Type::fromReflection()` builds a `Type` from the declared type of a property:

```php
use Svinx\Validator\Typing\Type;

$property = new \ReflectionProperty(User::class, 'birthday');

Type::fromReflection($property->getType())->describe();   // "DateTime | null"
```

Rules applied:

| Declared PHP type | Produced `Type` |
| --- | --- |
| `int`, `float`, `string`, `bool`, `array` | corresponding native type |
| `mixed`, `object`, `iterable` | `mixed` |
| `?X` (nullable) | `X \| null` |
| `BaseModel` class | `MODEL` |
| `TypeValidator` class | `VALIDATOR` |
| `enum` | `ENUM` |
| any other class | `OBJECT` |

This is exactly what `BaseModel` does for every property (see [Schemas](schemas.md)).

## Schemas

```php
Type::list(Type::of(EmailStr::class))->schema();
// [
//     'type' => 'array',
//     'items' => ['type' => 'string', 'format' => 'email'],
// ]
```

| Type | Schema |
| --- | --- |
| `int` / `float` / `bool` / `str` | `integer` / `number` / `boolean` / `string` |
| `null` | `type: null` |
| `list[T]` | `array` + `items` |
| `map[K, V]` | `object` + `additionalProperties` |
| `literal(...)` | `enum` |
| union | `anyOf` |
| model | `object` + `title` |
| validated type | `string` + `format` (`email`, `uri`, `uuid`, `date`...) |

## A complete example

!!! example "docs_src/Typing/Order.php"
    ```php
    namespace Svinx\Validator\Examples\Typing;

    use DateTime;
    use Svinx\Validator\BaseModel;
    use Svinx\Validator\Validators\EmailStr;

    class Order extends BaseModel
    {
        public int $count;
        public ?string $label = null;
        public array $tags = [];
        public EmailStr $owner;
        public ?DateTime $born = null;
        public OrderStatus $status;
    }
    ```

!!! example "docs_src/Typing/OrderStatus.php"
    ```php
    namespace Svinx\Validator\Examples\Typing;

    enum OrderStatus: string
    {
        case Pending = 'pending';
        case Paid = 'paid';
    }
    ```

    ```php
    use Svinx\Validator\Examples\Models\User;

    new Order([
    'count'  => 3,
    'owner'  => 'a@b.co',
    'status' => 'paid',          // converti en OrderStatus::Paid
    ]);
    ```

Next: [TypeAdapter](type-adapter.md).
