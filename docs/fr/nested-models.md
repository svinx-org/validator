# Modèles imbriqués

Un modèle peut contenir d'autres modèles. C'est la brique de base pour représenter des objets JSON structurés.

!!! example "docs_src/Models/Post.php"
    ```php
    <?php

    namespace Svinx\Validator\Examples\Models;

    use Svinx\Validator\BaseModel;

    class Post extends BaseModel
    {
        public string $title;
        public string $content;
        public User $author;
    }
    ```

`User` est le modèle de la page précédente. `Post` déclare simplement `public User $author;`.

## Construire depuis un tableau imbriqué

Le type de la propriété (`User`) est utilisé pour convertir le sous-tableau en objet :

```php
use Svinx\Validator\Examples\Models\Post;

$post = new Post([
    'title'   => 'Mon premier article',
    'content' => 'Lorem ipsum...',
    'author'  => [
        'user_id'   => 7,
        'full_name' => 'Alice',
    ],
]);

$post->author;                    // User
$post->author->name;              // "Alice"
```

Les alias du modèle imbriqué s'appliquent à ce niveau (`user_id`, pas `id`).

## Accepter aussi une instance déjà construite

Si la valeur est déjà une instance du modèle attendu, elle est utilisée telle quelle :

```php
$alice = new User(['user_id' => 7, 'full_name' => 'Alice']);

$post = new Post([
    'title'   => 'Mon article',
    'content' => 'Lorem',
    'author'  => $alice,
]);

$post->author === $alice;   // true
```

C'est pratique pour réutiliser un objet déjà validé sans le reconstruire.

## L'erreur remonte du niveau le plus profond

Une valeur invalide dans le modèle imbriqué fait échouer la construction du parent :

```php
new Post([
    'title'   => 'Mon article',
    'content' => 'Lorem',
    'author'  => ['user_id' => -1],    // gt: 0 violé
]);
// Svinx\Validator\ValidationError : Le champ 'id' doit être > 0
```

!!! note "Le message ne préfixe pas le chemin (`author.user_id`)"
    Le message désigne le champ fautif tel qu'il est déclaré dans son propre modèle. Ajoutez l'information de contexte dans votre couche applicative si vous en avez besoin.

## Sérialisation récursive

`model_dump()` descend dans les modèles imbriqués :

```php
$post->model_dump();
// [
//     'title'   => 'Mon premier article',
//     'content' => 'Lorem ipsum...',
//     'author'  => [
//         'user_id'   => 7,
//         'full_name' => 'Alice',
//         'role'      => 'user',
//         'birth_date'=> null,
//     ],
// ]
```

Les valeurs par défaut des modèles imbriqués apparaissent donc dans la sortie (`role`, `birth_date`).

## Tableaux

Une propriété `array` accepte un tableau **sans contraindre son contenu** : PHP n'a pas de génériques.

!!! example "docs_src/Validators/ContactList.php"
    ```php
    class ContactList extends BaseModel
    {
        public array $emails = [];
    }
    ```

    ```php
    $list = new ContactList(['emails' => ['a@b.co', 'c@d.co']]);
    $list->model_dump();     // ['emails' => ['a@b.co', 'c@d.co']]
    ```

Pour valider **le type des éléments**, utilisez un `TypeAdapter` (voir [Types et annotations](typing.md)) :

```php
use Svinx\Validator\TypeAdapter;
use Svinx\Validator\Typing\Type;
use Svinx\Validator\Validators\EmailStr;

$emails = TypeAdapter::of(Type::list(EmailStr::class));
$validated = $emails->validate(['a@b.co', 'c@d.co']);   // EmailStr[]
```

Le `model_dump()` d'un modèle contenant des modèles ou des types validés dans un tableau reste récursif :

```php
class Basket extends BaseModel
{
    public array $items = [];    // contenu libre
}

$basket->model_dump();   // les BaseModel et types validés présents sont sérialisés
```

## Modèles et `extra`

Par défaut les clés inconnues sont ignorées à chaque niveau. Pour interdire au niveau d'un modèle, voir [Configuration](configuration.md#extra).

## Récapitulatif

| Déclaration | Comportement |
| --- | --- |
| `public User $author;` | requis, accepte `array` ou `User` |
| `public ?User $author = null;` | optionnel |
| `public array $tags = [];` | tableau libre, aucun contrôle des éléments |
| `public Post $post;` | imbrication récursive (autant de niveaux que nécessaire) |

La suite : [types validés](validated-types.md).
