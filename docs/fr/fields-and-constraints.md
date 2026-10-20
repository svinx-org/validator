# Champs et contraintes

L'attribut `#[Field]` décrit **comment** un champ est lu, contraint et documenté.

## Toutes les options

| Option | Type | Effet |
| --- | --- | --- |
| `alias` | `string` | Nom de la clé dans les données d'entrée et dans `model_dump()` |
| `title` | `string` | Titre lisible (schéma) |
| `description` | `string` | Description (schéma) |
| `gt` / `ge` | `float\|int` | Strictement supérieur / supérieur ou égal (numériques) |
| `lt` / `le` | `float\|int` | Strictement inférieur / inférieur ou égal |
| `multiple_of` | `float\|int` | Multiple de |
| `min_length` / `max_length` | `int` | Longueur d'une chaîne (en caractères `mb`) |
| `pattern` | `string` | Expression régulière (`preg_match`) |
| `choices` | `array` | Liste fermée de valeurs autorisées |
| `default` | `mixed` | Valeur utilisée si la clé est absente |

```php
#[Field(alias: 'user_id', description: 'ID unique', gt: 0)]
public int $id;
```

## L'ordre des opérations

Pour chaque champ, la construction se déroule ainsi :

```
1. extraction de la valeur brute (alias, puis nom si populate_by_name)
2. normalisation des chaînes (Config : strip / lower / upper)
3. validateurs #[FieldValidator] en mode "before"
4. contraintes #[Field]  (gt, min_length, choices...)
5. validation / conversion du type (Type::validate)
6. validateurs #[FieldValidator] en mode "after"
7. affectation de la propriété
```

Conséquence importante : **les contraintes s'appliquent à la valeur brute**, avant la conversion. C'est ce qui permet ceci :

```php
#[Field(max_length: 10)]
public EmailStr $email;      // "tres.long@example.com" est refusé (10 caractères max)
```

Si les contraintes s'appliquaient après conversion, `max_length` n'aurait plus de sens : la valeur serait devenue un objet `EmailStr`. Voir [`docs_src/Validators/LimitedEmail.php`](https://github.com/svinx-org/validator/blob/main/docs_src/Validators/LimitedEmail.php).

## Champs requis, optionnels, par défaut

Un champ est **requis** quand la clé est absente *et* :

- aucune valeur par défaut n'est déclarée (`#[Field(default: ...)]` ou `= valeur`) ;
- le type n'autorise pas `null` (`?string`, `?int`...).

```php
new User([]);
// Svinx\Validator\ValidationError : Le champ 'id' est requis.
```

Les deux façons de fournir un défaut :

```php
#[Field(default: 'Anonyme')]     // défaut porté par l'attribut
public string $name;

public string $role = 'user';    // défaut porté par la propriété
```

!!! tip "Le défaut de l'attribut est prioritaire"
    `#[Field(default: 'x')] public string $name = 'y';` donne `'x'` quand la clé est absente.

Un champ **nullable** absorbe `null` sans contrainte :

```php
#[Field(alias: 'birth_date', min_length: 10)]
public ?\DateTime $birthday = null;
```

`null` n'est pas une chaîne : les contraintes de chaîne sont ignorées pour une valeur nulle (elles ne s'appliquent qu'aux valeurs présentes).

## Contraintes numériques

```php
#[Field(gt: 0, le: 150)]
public int $age;
```

| Valeur | Résultat |
| --- | --- |
| `0` | refusé (`doit être > 0`) |
| `1` | accepté |
| `151` | refusé (`doit être <= 150`) |

`multiple_of` accepte les flottants :

```php
#[Field(multiple_of: 0.5)]
public float $step;      // 1.5 ✔, 1.3 ✘
```

!!! warning "`gt` et `ge` sont exclusifs"
    Déclarer les deux (ou `lt` et `le`) lève une `InvalidArgumentException` à la construction de l'attribut.

## Contraintes de chaîne

```php
#[Field(min_length: 3, max_length: 20, pattern: '/^[a-z]+$/')]
public string $slug;
```

- les longueurs sont comptées en **caractères** (`mb_strlen`), pas en octets ;
- `pattern` est une expression régulière complète, délimiteurs inclus.

## Valeurs fermées : `choices`

```php
#[Field(choices: ['admin', 'user', 'guest'])]
public string $role = 'user';

new User(['user_id' => 1, 'role' => 'root']);
// Svinx\Validator\ValidationError : Le champ 'role' doit être une des valeurs : admin, user, guest
```

La comparaison est **stricte** (`===`) : `'1'` ne satisfait pas `choices: [1]`.

## Alias : entrée et sortie

`alias` change la clé lue **et** produite :

```php
#[Field(alias: 'user_id')]
public int $id;

$user = new User(['user_id' => 42]);
$user->id;            // 42
$user->model_dump();  // ['user_id' => 42, ...]
```

Avec `populate_by_name` (activé par défaut, voir [Configuration](configuration.md)), le nom de propriété est aussi accepté en entrée :

```php
new User(['id' => 42]);   // fonctionne aussi
```

## Alias aussi sur les modèles imbriqués

Rien de spécial : un modèle imbriqué construit avec ses propres alias, donc les deux niveaux se composent naturellement.

```php
$post = new Post([
    'title'   => 'Mon article',
    'content' => 'Lorem ipsum',
    'author'  => ['user_id' => 7, 'full_name' => 'Alice'],
]);
```

## Contraintes et schéma

Chaque option se retrouve dans `model_schema()` sous la forme JSON Schema correspondante :

| Option | JSON Schema |
| --- | --- |
| `gt` / `ge` | `exclusiveMinimum` / `minimum` |
| `lt` / `le` | `exclusiveMaximum` / `maximum` |
| `multiple_of` | `multipleOf` |
| `min_length` / `max_length` | `minLength` / `maxLength` |
| `pattern` | `pattern` |
| `choices` | `enum` |
| `title` / `description` / `default` | mêmes noms |

## Cas pratiques

### Identifiant auto-incrémenté

```php
#[Field(alias: 'id', gt: 0)]
public int $id;
```

### Prix en euros

```php
use Svinx\Validator\Validators\Decimal;

#[Field(ge: 0, description: 'Prix TTC en euros')]
public Decimal $price;
```

### Nom de fichier

```php
#[Field(pattern: '/^[\w\-. ]+\.(pdf|png|jpg)$/')]
public string $filename;
```

La suite : [modèles imbriqués](nested-models.md).
