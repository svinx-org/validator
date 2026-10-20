# Types et annotations

PHP n'a pas de génériques : impossible d'écrire `list<int>` dans une déclaration de propriété. `Svinx\Validator\Typing` comble ce manque avec des **objets qui décrivent un type**.

```php
use Svinx\Validator\Typing\Type;

Type::int();                              // int
Type::list(Type::int());                  // list[int]
Type::map(Type::str(), Type::int());      // map[string, int]
Type::optional(Type::of(EmailStr::class));// EmailStr | null
Type::literal('a', 'b');                  // literal('a', 'b')
```

Un `Type` sait :

| Méthode | Rôle |
| --- | --- |
| `validate($value, $bindings = [], $label = null)` | Valide et convertit une valeur brute |
| `dump($value, $bindings = [])` | Convertit une valeur validée en valeur sérialisable |
| `schema()` | Renvoie le schéma JSON du type |
| `matches($value)` | Contrôle léger, sans conversion |
| `describe()` | Nom lisible (`list[int]`, `int \| null`...) |
| `isNullable()` | Vrai si `null` est accepté |
| `withConstraints(FieldInfo ...)` | Ajoute des contraintes (voir `Annotated`) |
| `lax()` | Active la coercition des scalaires |

## Fabriques

| Fabrique | Type produit | `describe()` |
| --- | --- | --- |
| `Type::any()` | n'importe quoi | `mixed` |
| `Type::null()` | `null` | `null` |
| `Type::bool()` | booléen | `bool` |
| `Type::int()` | entier | `int` |
| `Type::float()` | nombre | `float` |
| `Type::str()` | chaîne | `string` |
| `Type::array()` | tableau libre | `array` |
| `Type::list($item)` | liste homogène | `list[int]` |
| `Type::map($key, $value)` | dictionnaire | `map[string, int]` |
| `Type::union(...$types)` | union | `int \| string` |
| `Type::optional($type)` | `$type` ou `null` | `int \| null` |
| `Type::literal(...$valeurs)` | valeurs fermées | `literal('a', 'b')` |
| `Type::of($classe)` | selon la classe | `EmailStr`, `User`, ... |
| `Type::model($classe)` | modèle de validation | `User` |
| `Type::validator($classe)` | type validé | `EmailStr` |
| `Type::enum($enum)` | énumération PHP | `OrderStatus` |
| `Type::object($classe)` | autre objet | `DateTime` |
| `Type::typevar($var)` | variable de type | `$T` |

`Type::of()` détecte automatiquement la bonne fabrique :

```php
Type::of(EmailStr::class);    // VALIDATOR
Type::of(OrderStatus::class); // ENUM
Type::of(User::class);        // MODEL
Type::of(DateTime::class);    // OBJECT
Type::of('int');              // INT (les types natifs sont aussi acceptés)
```

Les constantes de `kind` sont exposées : `Type::INT`, `Type::LIST`, `Type::UNION`...

## Valider

```php
use Svinx\Validator\Typing\Type;

Type::int()->validate(42);            // 42
Type::int()->validate('42');          // ✘ ValidationError : Valeur : int attendu, string donné ('42').

Type::int()->lax()->validate('42');   // 42  (coercition explicite)
Type::int()->lax()->validate('4.5');  // ✘
```

Les scalaires sont **stricts** par défaut. `lax()` est la seule façon d'obtenir une coercition, et elle est propagée aux membres :

```php
$type = Type::map(Type::str(), Type::int())->lax();

$type->validate(['page' => '2']);     // ['page' => 2]
```

### Listes et dictionnaires

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

!!! note "Clés de tableau"
    PHP convertit les clés numériques en `int`. `Type::map(Type::str(), …)` rétablit les clés en chaînes pour ne pas échouer sur `'7'`.

### Unions et valeurs nulles

```php
$type = Type::union(Type::int(), Type::str());

$type->validate(1);        // 1
$type->validate('abc');    // "abc"
$type->validate(1.5);      // ✘ aucune branche ne correspond
```

`Type::optional(Type::int())` est un raccourci pour `Type::union(Type::int(), Type::null())` :

```php
Type::optional(Type::int())->validate(null);   // null
```

### Valeurs fermées et énumérations

```php
Type::literal('a', 'b')->validate('a');   // "a"

use Svinx\Validator\Examples\Typing\OrderStatus;

Type::of(OrderStatus::class)->validate('paid');   // OrderStatus::Paid
```

### Modèles de validation

```php
use Svinx\Validator\Examples\Models\User;

$type = Type::of(User::class);

$user = $type->validate(['user_id' => 1, 'full_name' => 'Alice']);  // User
$type->validate($user);                                             // même instance
```

## Types construits à la main : `ListType` et `Map`

Les classes `ListType` et `Map` sont les objets sous-jacents ; elles sont utiles pour inspecter un type :

```php
use Svinx\Validator\Typing\ListType;
use Svinx\Validator\Typing\Map;

$list = ListType::make(Type::int());
$list->item();          // Type(int)

$map = Map::make(Type::str(), Type::int());
$map->keyType();        // Type(string)
$map->valueType();      // Type(int)
```

## `TypeVar` : les variables de type

`TypeVar` décrit un type inconnu à l'écriture, résolu plus tard par des *bindings* :

```php
use Svinx\Validator\Typing\TypeVar;

$t = TypeVar::of('T');

$type = Type::list($t);
$type->validate([1, 2], ['T' => Type::int()]);    // [1, 2]
$type->validate(['a'], ['T' => Type::str()]);     // ["a"]
$type->validate([1]);                             // ✘ Variable de type non liée : T.
```

Bornes et défauts :

```php
$bound   = TypeVar::of('T', Type::int());       // borne : int
$default = TypeVar::of('U', null, Type::str()); // défaut : string
```

Un conteneur `lax()` propage la coercition à ses variables liées :

```php
Type::list($t)->lax()->validate(['1'], ['T' => Type::int()]);   // [1]
```

## `Annotated` : un type avec des contraintes

`Annotated::of()` attache des `FieldInfo` (contraintes) et des métadonnées libres à un type :

```php
use Svinx\Validator\Field;
use Svinx\Validator\Typing\Annotated;

$type = Annotated::of(Type::str(), new Field(min_length: 3, max_length: 5));

$type->validate('abcd');    // "abcd"
$type->validate('ab');      // ✘ doit avoir au moins 3 caractères
$type->schema();            // ['type' => 'string', 'minLength' => 3, 'maxLength' => 5]
```

Les `FieldInfo` deviennent des contraintes, les autres valeurs deviennent des métadonnées :

```php
$type = Annotated::of(Type::int(), 'identifiant métier', 42);

$type->metadata;   // ['identifiant métier', 42]
```

!!! note "Pas de `pydantic.Field` dans les génériques PHP"
    Pydantic écrit `Annotated[int, Field(gt=0)]` ; ici, c'est un objet construit à l'exécution : `Annotated::of(Type::int(), new Field(gt: 0))`.

## Depuis la réflexion

`Type::fromReflection()` construit un `Type` à partir du type déclaré d'une propriété :

```php
use Svinx\Validator\Typing\Type;

$property = new \ReflectionProperty(User::class, 'birthday');

Type::fromReflection($property->getType())->describe();   // "DateTime | null"
```

Règles appliquées :

| Type PHP déclaré | `Type` produit |
| --- | --- |
| `int`, `float`, `string`, `bool`, `array` | type natif correspondant |
| `mixed`, `object`, `iterable` | `mixed` |
| `?X` (nullable) | `X \| null` |
| classe `BaseModel` | `MODEL` |
| classe `TypeValidator` | `VALIDATOR` |
| `enum` | `ENUM` |
| autre classe | `OBJECT` |

C'est exactement ce que fait `BaseModel` pour chaque propriété (voir [Schémas](schemas.md)).

## Schémas

```php
Type::list(Type::of(EmailStr::class))->schema();
// [
//     'type' => 'array',
//     'items' => ['type' => 'string', 'format' => 'email'],
// ]
```

| Type | Schéma |
| --- | --- |
| `int` / `float` / `bool` / `str` | `integer` / `number` / `boolean` / `string` |
| `null` | `type: null` |
| `list[T]` | `array` + `items` |
| `map[K, V]` | `object` + `additionalProperties` |
| `literal(...)` | `enum` |
| union | `anyOf` |
| modèle | `object` + `title` |
| type validé | `string` + `format` (`email`, `uri`, `uuid`, `date`...) |

## Un exemple complet

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

La suite : [TypeAdapter](type-adapter.md).
