# Errors

Every validation error is raised as a `Svinx\Validator\ValidationError`, which extends `\InvalidArgumentException`.

```php
namespace Svinx\Validator;

class ValidationError extends \InvalidArgumentException {}
```

## Catching an error

```php
use Svinx\Validator\Examples\Models\User;
use Svinx\Validator\ValidationError;

try {
    $user = new User($payload);
} catch (ValidationError $e) {
    // $e->getMessage() : message lisible
}
```

Because `ValidationError` is an `InvalidArgumentException`, you can also catch the SPL type:

```php
try {
    $user = new User($payload);
} catch (\InvalidArgumentException $e) {
    // ...
}
```

## "First failure" behaviour

Construction stops at the **first** error encountered: a single message is raised, not a list.

```
extraction → before → contraintes → type → after → affectation
```

The traversal order is the declaration order of the properties in the class.

!!! note "No multi-error aggregation yet"
>    Pydantic v2 returns a `ValidationError` containing *all* the errors. This is not implemented here yet. For a form that must display every error, validate field by field with independent `TypeAdapter`s.

## The messages, step by step

### Missing field

```
Le champ 'id' est requis.
```

A field is required when it has neither a default nor a nullable type.

### Invalid type

```
Le champ 'id' : int attendu, string donné ('42').
```

The format is `The field '<name>': <expected type> expected, <received type> given (<value>)`.

| Expected type | Example message |
| --- | --- |
| `int` | `The field 'count': int expected, string given ('3').` |
| `EmailStr` | `The field 'email': EmailStr expected, array given.` |
| `OrderStatus` | `The field 'status': OrderStatus expected, string given ('refunded').` |
| union | `The field 'x': no branch of int \| null matches…` |

### `#[Field]` constraints

```
Le champ 'id' doit être > 0
Le champ 'age' doit être >= 18
Le champ 'name' doit avoir au moins 2 caractères
Le champ 'name' ne doit pas dépasser 50 caractères
Le champ 'slug' ne correspond pas au pattern attendu
Le champ 'role' doit être une des valeurs : admin, user, guest
Le champ 'price' doit être multiple de 0.5
```

### Validated types

The messages come from the type itself:

```
EmailStr invalide : 'pas-un-email' n'est pas une adresse email valide.
HttpUrl invalide : schéma "ftp" non autorisé (attendu : http ou https).
UUID invalide : 'x' n'est pas un UUID valide.
Datetime invalide : 'demain' ne respecte pas le format ISO 8601 (AAAA-MM-JJTHH:MM:SS).
IPAddress invalide : …
```

### List and map elements

```
Élément 1 de la valeur : int attendu, string donné ('deux').
Valeur de la clé 'age' de la valeur : int attendu, string donné ('abc').
```

### Declaration errors

These errors signal a **code bug**, not invalid data:

| Situation | Message |
| --- | --- |
| unknown `#[FieldValidator]` target | `The validator 'Class::method' targets the unknown field 'x'.` |
| validator not public / static / abstract | `The validator 'Class::method' must be a public, non-static and non-abstract method.` |
| validator returning an incompatible type | `The validator of field 'x' returned an incompatible type (expected: string, array given).` |
| unexpected exception inside a validator | `Validation of field 'x' failed (method): <message>` |
| `TypeError` inside a validator | `The validator 'method' of field 'x' received an incompatible value: <message>` |
| `extra: forbid` | `Unknown fields: a, b.` |

### Configuration errors

Raised when the configuration is declared (`InvalidArgumentException`):

```
Option extra inconnue : "bogus" (attendu : ignore, forbid ou allow).
alias_generator doit être callable ou null.
Options de configuration inconnues : inconnu
```

## Best practices

### Translating messages at the application level

The messages are in French and intended for developers. For a multilingual interface, map the messages or wrap the construction:

```php
try {
    $user = new User($payload);
} catch (ValidationError $e) {
    $this->errors[] = $this->translator->translate($e->getMessage());
}
```

### Customizing a validator message

The message of a `#[FieldValidator]` is the one you write:

```php
throw new ValidationError('Ce nom d\'utilisateur est déjà pris.');
```

### Do not swallow the error

A `ValidationError` must bubble up to the layer that makes the decision (HTTP 422 response, console message, DLQ...). The model is **never** built partially: there is no half-filled object to inspect.

Next: [API Reference](reference.md).
