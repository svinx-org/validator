# Validateurs personnalisés

`#[Field]` couvre les contraintes génériques. Pour vos règles métier, écrivez une méthode : c'est l'équivalent PHP de `@field_validator` de Pydantic.

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

| Paramètre | Rôle |
| --- | --- |
| `$cls` | Nom de la classe du modèle (`static::class`) — l'équivalent de `cls` chez Pydantic |
| `$value` | Valeur courante (brute en mode `before`, typée en mode `after`) |

## Choisir la cible

| Forme | Cible |
| --- | --- |
| `#[FieldValidator('name')]` | La propriété `name` |
| `#[FieldValidator('username')]` | L'**alias** de la propriété concernée (résolution automatique) |
| `#[FieldValidator(['nickname', 'age'])]` | Plusieurs champs |
| `#[FieldValidator(FieldValidator::ANY)]` | Tous les champs (`'*'`) |
| `#[FieldValidator]` (sans argument) | Cible déduite du nom : `validate_<champ>` |

```php
#[FieldValidator]
public function validate_birth_date(string $cls, mixed $value): mixed
{
    // cible automatiquement le champ `birth_date`
}
``` 

!!! warning "`validate_` est le seul préfixe reconnu"
    Une méthode `check_code` sans argument de cible cherchera un champ nommé `check_code`. Utilisez `#[FieldValidator('code')]` dans ce cas.

## Les deux modes

### `after` (par défaut)

Le validateur reçoit la valeur **déjà typée et contrainte** :

```php
#[FieldValidator('username')]
public function validate_username(string $cls, string $value): string
{
    // $value est une string déjà validée par #[Field(min_length: 2)]
}
```

C'est le mode adapté aux règles métier : comparaison, normalisation, vérification d'unicité.

### `before`

Le validateur reçoit la valeur **brute**, avant le contrôle de type et les contraintes :

```php
#[FieldValidator(mode: FieldValidator::BEFORE)]
public function validate_birth_date(string $cls, mixed $value): mixed
{
    return is_string($value) ? new DateTime($value) : $value;
}
```

Ce mode sert à **coercer** : transformer une chaîne ISO en `\DateTime`, un code pays en objet, etc.

```
extraction → before → contraintes #[Field] → type → after → affectation
```

## La valeur de retour

| Retour | Effet |
| --- | --- |
| une valeur | remplace la valeur courante |
| `null` | la valeur courante est conservée |
| méthode déclarée `void` | le retour est ignoré (le validateur valide, sans transformer) |

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

## Les valeurs nulles

Par défaut, un validateur **n'est pas exécuté** quand la valeur est `null` :

```php
#[FieldValidator('note')]
public function check_note(string $cls, string $value): string
{
    return $value . '!';
}

$account = new StrictAccount();   // note vaut null → le validateur est ignoré
```

Pour forcer l'exécution (et fournir une valeur par défaut, par exemple), utilisez `always: true` :

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

## Les erreurs

### Lever une erreur métier

```php
throw new ValidationError("Le username ne doit pas contenir d'espace.");
```

`ValidationError` remonte **telle quelle** : le message est celui que vous avez écrit.

### Les autres exceptions sont converties

Une exception inattendue dans un validateur n'échappe pas au contrat : elle est encapsulée dans une `ValidationError`.

```
Échec de la validation du champ 'x' (ma_methode) : <message original>
```

Un `TypeError` (par exemple un paramètre typé `string $value` qui reçoit `null`) est lui aussi converti :

```
Le validateur 'ma_methode' du champ 'x' a reçu une valeur incompatible : …
```

### Erreurs de déclaration

Deux erreurs sont détectées **au moment de la construction du modèle** :

| Déclaration | Erreur |
| --- | --- |
| cible inconnue (`#[FieldValidator('champ_inexistant')]`) | `Le validateur 'Classe::méthode' cible le champ inconnu 'champ_inexistant'.` |
| méthode non publique, statique ou abstraite | `Le validateur 'Classe::méthode' doit être une méthode publique, non statique et non abstraite.` |

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

### Retour de type incompatible

Si un validateur retourne une valeur qui ne peut pas être affectée à la propriété typée, PHP lève un `TypeError`, converti en `ValidationError` explicite :

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

## Ordre d'exécution

Pour un même champ, plusieurs validateurs s'exécutent dans l'**ordre de déclaration** des méthodes, en filtrant par mode :

```php
#[FieldValidator('name')]           // 1er (after)
public function normalise(string $cls, string $value): string { /* ... */ }

#[FieldValidator('name', mode: 'before')]   // avant même les contraintes
public function prepare(string $cls, mixed $value): mixed { /* ... */ }
```

Pour un champ donné : tous les `before` (ordre de déclaration), puis le type et les contraintes, puis tous les `after`.

## Exemples réels

### Nom d'utilisateur

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

### Compatibilité de deux champs

Un validateur ne voit qu'un champ : pour comparer deux valeurs, on utilise la cible `*` (ou une méthode de contrôle appelée depuis plusieurs validateurs) :

```php
#[FieldValidator(['password', 'password_confirmation'])]
public function validate_password(string $cls, string $value): mixed
{
    // appelé pour chaque champ ; la comparaison croisée se fait ici
    return $value;
}
```

!!! note "Pas encore de `model_validator`"
    Pydantic propose `@model_validator(mode='after')` pour valider le modèle entier. Ce n'est pas encore disponible : passez par la cible `*` ou un contrôle explicite après construction.

### Enrichissement

```php
#[FieldValidator(mode: FieldValidator::BEFORE)]
public function parse_country(string $cls, mixed $value): mixed
{
    return $value instanceof Country ? $value : Country::fromCode((string) $value);
}
```

La suite : [types et annotations](typing.md).
