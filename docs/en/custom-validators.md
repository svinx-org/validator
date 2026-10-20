# Custom validators

`#[Field]` covers the generic constraints. For your business rules, write a method: it is the PHP equivalent of Pydantic's `@field_validator`.

!!! example "docs_src/FieldValidators/Account.php"
    ```php
    <?php

    namespace Svinx\Validator\Examples\FieldValidators;

    use DateTime;
    use Svinx\Validator\BaseModel;
    use Svinx\Validator\Field;
    use Svinx\Validator\FieldValidator;
    use Svinx\Validator\ValidationError;

    class Account extends BaseModel
    {
        #[Field(alias: 'user_id', gt: 0)]
        public int $id;

        #[Field(alias: 'username', min_length: 2)]
        public string $name = 'Anonyme';

        #[Field(alias: 'birth_date')]
        public ?DateTime $birthday = null;

        public string $nickname = '';

        public int $age = 0;

        #[FieldValidator('username')]
        public function validate_username(string $cls, string $value): string
        {
            if (str_contains($value, ' ')) {
                throw new ValidationError("Le username ne doit pas contenir d'espace.");
            }

            return strtoupper($value);
        }

        #[FieldValidator(mode: FieldValidator::BEFORE)]
        public function validate_birth_date(string $cls, mixed $value): mixed
        {
            return is_string($value) ? new DateTime($value) : $value;
        }

        #[FieldValidator(['nickname', 'age'])]
        public function validate_multi(string $cls, mixed $value): mixed
        {
            return is_int($value) ? $value * 2 : strtoupper($value);
        }

        #[FieldValidator(FieldValidator::ANY)]
        public function validate_everything(string $cls, mixed $value): mixed
        {
            return $value;
        }
    }
    ```

## Signature

```php
#[FieldValidator('champ')]
public function ma_regle(string $cls, mixed $value): mixed
```

| Parameter | Role |
| --- | --- |
| `$cls` | Name of the model class (`static::class`) — the equivalent of `cls` in Pydantic |
| `$value` | Current value (raw in `before` mode, typed in `after` mode) |

## Choosing the target

| Form | Target |
| --- | --- |
| `#[FieldValidator('name')]` | The `name` property |
| `#[FieldValidator('username')]` | The **alias** of the relevant property (resolved automatically) |
| `#[FieldValidator(['nickname', 'age'])]` | Several fields |
| `#[FieldValidator(FieldValidator::ANY)]` | All fields (`'*'`) |
| `#[FieldValidator]` (no argument) | Target inferred from the name: `validate_<field>` |

```php
#[FieldValidator]
public function validate_birth_date(string $cls, mixed $value): mixed
{
    // cible automatiquement le champ `birth_date`
}
```

!!! warning "`validate_` is the only recognized prefix"
    A `check_code` method with no target argument looks for a field named `check_code`. Use `#[FieldValidator('code')]` in that case.

## The two modes

### `after` (the default)

The validator receives the value **already typed and constrained**:

```php
#[FieldValidator('username')]
public function validate_username(string $cls, string $value): string
{
    // $value est une string déjà validée par #[Field(min_length: 2)]
}
```

This is the mode suited to business rules: comparison, normalization, uniqueness checks.

### `before`

The validator receives the **raw** value, before the type check and the constraints:

```php
#[FieldValidator(mode: FieldValidator::BEFORE)]
public function validate_birth_date(string $cls, mixed $value): mixed
{
    return is_string($value) ? new DateTime($value) : $value;
}
```

This mode is meant for **coercion**: turning an ISO string into a `\DateTime`, a country code into an object, and so on.

```
extraction → before → #[Field] constraints → type → after → assignment
```

## The return value

| Return | Effect |
| --- | --- |
| a value | replaces the current value |
| `null` | the current value is kept |
| a method declared `void` | the return is ignored (the validator validates without transforming) |

```php
#[FieldValidator('code')]
public function check_code(string $cls, string $value): void
{
    if ($value !== 'ok') {
        throw new ValidationError('Code invalide.');
    }
}
```

```php
use Svinx\Validator\Examples\FieldValidators\Account;

$account = new Account(['user_id' => 1, 'username' => 'eorbah']);

$account->name;      // "EORBAH" — le retour du validateur remplace la valeur
```

## Null values

By default, a validator **does not run** when the value is `null`:

```php
#[FieldValidator('note')]
public function check_note(string $cls, string $value): string
{
    return $value . '!';
}

$account = new StrictAccount();   // note vaut null → le validateur est ignoré
```

To force execution (and supply a default value, for example), use `always: true`:

!!! example "docs_src/FieldValidators/AlwaysAccount.php"
    ```php
    class AlwaysAccount extends BaseModel
    {
        public ?string $note = null;

        #[FieldValidator('note', always: true)]
        public function check_note(string $cls, ?string $value): string
        {
            return $value ?? 'défaut';
        }
    }
    ```

    ```php
    use Svinx\Validator\Examples\FieldValidators\AlwaysAccount;

    (new AlwaysAccount([]))->note;   // "défaut"
    ```

## Errors

### Raising a business error

```php
throw new ValidationError("Le username ne doit pas contenir d'espace.");
```

`ValidationError` propagates **as is**: the message is the one you wrote.

### Other exceptions are converted

An unexpected exception inside a validator does not escape the contract: it is wrapped in a `ValidationError`.

```
Échec de la validation du champ 'x' (ma_methode) : <message original>
```

A `TypeError` (for example a `string $value` parameter that receives `null`) is converted too:

```
Le validateur 'ma_methode' du champ 'x' a reçu une valeur incompatible : …
```

### Declaration errors

Two errors are detected **when the model is constructed**:

| Declaration | Error |
| --- | --- |
| unknown target (`#[FieldValidator('champ_inexistant')]`) | `Validator 'Class::method' targets the unknown field 'champ_inexistant'.` |
| method that is not public, or is static or abstract | `Validator 'Class::method' must be a public, non-static and non-abstract method.` |

!!! example "docs_src/FieldValidators/BrokenAccount.php"
    ```php
    class BrokenAccount extends BaseModel
    {
        public string $name = '';

        #[FieldValidator('champ_inexistant')]
        public function validate_ghost(string $cls, mixed $value): mixed
        {
            return $value;
        }
    }

    new BrokenAccount(['name' => 'x']);   // ValidationError : champ inconnu
    ```

### Incompatible return type

If a validator returns a value that cannot be assigned to the typed property, PHP raises a `TypeError`, which is converted into an explicit `ValidationError`:

!!! example "docs_src/FieldValidators/CoercingAccount.php"
    ```php
    class CoercingAccount extends BaseModel
    {
        public string $name = '';

        #[FieldValidator('name')]
        public function validate_name(string $cls, mixed $value): mixed
        {
            return ['invalide'];    // un tableau pour une propriété string
        }
    }

    new CoercingAccount(['name' => 'x']);
    // ValidationError : Le validateur du champ 'name' a retourné un type incompatible
    ```

## Execution order

For the same field, several validators run in the **declaration order** of the methods, filtered by mode:

```php
#[FieldValidator('name')]           // 1er (after)
public function normalise(string $cls, string $value): string { /* ... */ }

#[FieldValidator('name', mode: 'before')]   // avant même les contraintes
public function prepare(string $cls, mixed $value): mixed { /* ... */ }
```

For a given field: all the `before` validators (in declaration order), then the type and the constraints, then all the `after` validators.

## Real-world examples

### Username

```php
#[FieldValidator('username')]
public function validate_username(string $cls, string $value): string
{
    if (preg_match('/\s/', $value)) {
        throw new ValidationError("Le username ne doit pas contenir d'espace.");
    }

    return strtolower($value);
}
```

### Compatibility between two fields

A validator only sees a single field: to compare two values, use the `*` target (or a check method called from several validators):

```php
#[FieldValidator(['password', 'password_confirmation'])]
public function validate_password(string $cls, string $value): mixed
{
    // appelé pour chaque champ ; la comparaison croisée se fait ici
    return $value;
}
```

!!! note "No `model_validator` yet"
    Pydantic offers `@model_validator(mode='after')` to validate the whole model. This is not available yet: go through the `*` target or an explicit check after construction.

### Enrichment

```php
#[FieldValidator(mode: FieldValidator::BEFORE)]
public function parse_country(string $cls, mixed $value): mixed
{
    return $value instanceof Country ? $value : Country::fromCode((string) $value);
}
```

Next: [types and annotations](typing.md).
