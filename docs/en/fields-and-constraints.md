# Fields and constraints

The `#[Field]` attribute describes **how** a field is read, constrained and documented.

## All the options

| Option | Type | Effect |
| --- | --- | --- |
| `alias` | `string` | Name of the key in the input data and in `model_dump()` |
| `title` | `string` | Human-readable title (schema) |
| `description` | `string` | Description (schema) |
| `gt` / `ge` | `float\|int` | Strictly greater than / greater than or equal to (numeric fields) |
| `lt` / `le` | `float\|int` | Strictly less than / less than or equal to |
| `multiple_of` | `float\|int` | Multiple of |
| `min_length` / `max_length` | `int` | Length of a string (in `mb` characters) |
| `pattern` | `string` | Regular expression (`preg_match`) |
| `choices` | `array` | Closed set of allowed values |
| `default` | `mixed` | Value used when the key is missing |

```php
#[Field(alias: 'user_id', description: 'ID unique', gt: 0)]
public int $id;
```

## Order of operations

For each field, construction runs as follows:

```
1. raw value extraction (alias, then name if populate_by_name)
2. string normalization (Config: strip / lower / upper)
3. #[FieldValidator] validators in "before" mode
4. #[Field] constraints  (gt, min_length, choices...)
5. type validation / conversion (Type::validate)
6. #[FieldValidator] validators in "after" mode
7. property assignment
```

An important consequence: **constraints apply to the raw value**, before conversion. That is what makes this possible:

```php
#[Field(max_length: 10)]
public EmailStr $email;      // "tres.long@example.com" est refusé (10 caractères max)
```

If constraints were applied after conversion, `max_length` would no longer make sense: the value would already have become an `EmailStr` object. See [`docs_src/Validators/LimitedEmail.php`](https://github.com/svinx-org/validator/blob/main/docs_src/Validators/LimitedEmail.php).

## Required, optional and defaulted fields

A field is **required** when the key is missing *and*:

- no default value is declared (`#[Field(default: ...)]` or `= value`);
- the type does not allow `null` (`?string`, `?int`...).

```php
new User([]);
// Svinx\Validator\ValidationError : Le champ 'id' est requis.
```

The two ways to supply a default:

```php
#[Field(default: 'Anonyme')]     // défaut porté par l'attribut
public string $name;

public string $role = 'user';    // défaut porté par la propriété
```

!!! tip "The attribute default wins"
    `#[Field(default: 'x')] public string $name = 'y';` yields `'x'` when the key is missing.

A **nullable** field absorbs `null` without any constraint:

```php
#[Field(alias: 'birth_date', min_length: 10)]
public ?\DateTime $birthday = null;
```

`null` is not a string: string constraints are skipped for a null value (they only apply to values that are present).

## Numeric constraints

```php
#[Field(gt: 0, le: 150)]
public int $age;
```

| Value | Result |
| --- | --- |
| `0` | rejected (`doit être > 0`) |
| `1` | accepted |
| `151` | rejected (`doit être <= 150`) |

`multiple_of` accepts floats:

```php
#[Field(multiple_of: 0.5)]
public float $step;      // 1.5 ✔, 1.3 ✘
```

!!! warning "`gt` and `ge` are mutually exclusive"
    Declaring both (or `lt` and `le`) raises an `InvalidArgumentException` when the attribute is constructed.

## String constraints

```php
#[Field(min_length: 3, max_length: 20, pattern: '/^[a-z]+$/')]
public string $slug;
```

- lengths are counted in **characters** (`mb_strlen`), not in bytes;
- `pattern` is a complete regular expression, delimiters included.

## Closed sets of values: `choices`

```php
#[Field(choices: ['admin', 'user', 'guest'])]
public string $role = 'user';

new User(['user_id' => 1, 'role' => 'root']);
// Svinx\Validator\ValidationError : Le champ 'role' doit être une des valeurs : admin, user, guest
```

The comparison is **strict** (`===`): `'1'` does not satisfy `choices: [1]`.

## Aliases: input and output

`alias` changes both the key that is read **and** the key that is produced:

```php
#[Field(alias: 'user_id')]
public int $id;

$user = new User(['user_id' => 42]);
$user->id;            // 42
$user->model_dump();  // ['user_id' => 42, ...]
```

With `populate_by_name` (enabled by default, see [Configuration](configuration.md)), the property name is also accepted as input:

```php
new User(['id' => 42]);   // fonctionne aussi
```

## Aliases on nested models too

Nothing special: a nested model is built with its own aliases, so the two levels compose naturally.

```php
$post = new Post([
    'title'   => 'Mon article',
    'content' => 'Lorem ipsum',
    'author'  => ['user_id' => 7, 'full_name' => 'Alice'],
]);
```

## Constraints and schema

Every option shows up in `model_schema()` as the matching JSON Schema keyword:

| Option | JSON Schema |
| --- | --- |
| `gt` / `ge` | `exclusiveMinimum` / `minimum` |
| `lt` / `le` | `exclusiveMaximum` / `maximum` |
| `multiple_of` | `multipleOf` |
| `min_length` / `max_length` | `minLength` / `maxLength` |
| `pattern` | `pattern` |
| `choices` | `enum` |
| `title` / `description` / `default` | same names |

## Common recipes

### Auto-incremented identifier

```php
#[Field(alias: 'id', gt: 0)]
public int $id;
```

### Price in euros

```php
use Svinx\Validator\Validators\Decimal;

#[Field(ge: 0, description: 'Prix TTC en euros')]
public Decimal $price;
```

### File name

```php
#[Field(pattern: '/^[\w\-. ]+\.(pdf|png|jpg)$/')]
public string $filename;
```

Next: [nested models](nested-models.md).
