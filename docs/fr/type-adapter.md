# TypeAdapter

`TypeAdapter` valide **une valeur isolée**, sans modèle. C'est l'équivalent de `TypeAdapter` de Pydantic.

```php
use Svinx\Validator\TypeAdapter;
use Svinx\Validator\Typing\Type;
use Svinx\Validator\Validators\EmailStr;

$adapter = TypeAdapter::of(Type::list(EmailStr::class));

$emails = $adapter->validate(['a@b.co', 'c@d.co']);   // EmailStr[]
$adapter->dump($emails);                              // ['a@b.co', 'c@d.co']
```

## Construire un adaptateur

| Fabrique | Usage |
| --- | --- |
| `TypeAdapter::of(Type|string $type, array $bindings = [])` | Depuis un `Type`, une classe ou un type natif |
| `TypeAdapter::of(...)->lax()` | Variante avec coercition des scalaires |
| `TypeAdapter::model(string $class)` | Adaptateur d'un modèle complet |

```php
TypeAdapter::of(EmailStr::class);        // une classe
TypeAdapter::of('int');                  // un type natif
TypeAdapter::of(Type::list(Type::int()));// un type composé
TypeAdapter::model(User::class);         // un modèle
```

`TypeAdapter::model()` refuse une classe qui n'est pas un modèle :

```php
TypeAdapter::model(\stdClass::class);   // ✘ n'est pas un modèle de validation
```

## Valider, sérialiser

| Méthode | Rôle |
| --- | --- |
| `validate($value)` | Valide et convertit |
| `validate_json(string $json)` | Décode le JSON puis valide (`\JsonException` si le JSON est malformé) |
| `dump($value)` | Convertit en valeur sérialisable |
| `dump_json($value, $flags = 0)` | `dump()` + `json_encode` |
| `json_schema()` | Schéma JSON du type |
| `json_schema_json($flags = JSON_PRETTY_PRINT)` | Schéma JSON encodé |

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

## Avec des variables de type

Les *bindings* sont fournis à la construction :

```php
use Svinx\Validator\Typing\TypeVar;

$t = TypeVar::of('T');

$adapter = TypeAdapter::of(Type::list($t), ['T' => Type::int()]);
$adapter->validate([1, 2]);     // [1, 2]
$adapter->validate(['a']);      // ✘ Élément 0 ... int attendu
```

## Un modèle entier

```php
use Svinx\Validator\Examples\Models\User;

$adapter = TypeAdapter::model(User::class);

$user = $adapter->validate(['user_id' => 1, 'full_name' => 'Alice']);   // User
$adapter->dump($user);      // tableau avec alias
$adapter->json_schema();    // ['type' => 'object', 'title' => User::class]
```

!!! note "Quand utiliser `TypeAdapter` ?"
    - valider une valeur qui n'est pas un modèle (un identifiant, une liste d'emails, une map de compteurs) ;
    - valider au niveau d'un paramètre de fonction ;
    - produire un schéma JSON pour une valeur isolée (query string, champ de formulaire, webhook).

## Quand préférer un modèle

Un `TypeAdapter` ne décrit pas plusieurs champs nommés. Dès qu'il y a plusieurs champs liés, écrivez un `BaseModel` : vous gagnez les alias, les contraintes par champ, les validateurs et `model_dump()`.

```php
// Adapter : une valeur
TypeAdapter::of(Type::map(Type::str(), Type::of(UUID::class)))->validate($data);

// Modèle : plusieurs champs
new Order(['count' => 3, 'owner' => 'a@b.co', 'status' => 'paid']);
```

La suite : [Configuration](configuration.md).
