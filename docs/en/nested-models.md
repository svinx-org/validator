# Nested models

A model can contain other models. This is the basic building block for representing structured JSON objects.

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

`User` is the model from the previous page. `Post` simply declares `public User $author;`.

## Building from a nested array

The property type (`User`) is used to convert the sub-array into an object:

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

The aliases of the nested model apply at this level (`user_id`, not `id`).

## Accepting an already-built instance

If the value is already an instance of the expected model, it is used as is:

```php
$alice = new User(['user_id' => 7, 'full_name' => 'Alice']);

$post = new Post([
    'title'   => 'Mon article',
    'content' => 'Lorem',
    'author'  => $alice,
]);

$post->author === $alice;   // true
```

This is handy for reusing an object that has already been validated without rebuilding it.

## The error surfaces from the deepest level

An invalid value inside the nested model makes the parent construction fail:

```php
new Post([
    'title'   => 'Mon article',
    'content' => 'Lorem',
    'author'  => ['user_id' => -1],    // gt: 0 violé
]);
// Svinx\Validator\ValidationError : Le champ 'id' doit être > 0
```

!!! note "The message does not prefix the path (`author.user_id`)"
    The message names the offending field as it is declared in its own model. Add the context information in your application layer if you need it.

## Recursive serialization

`model_dump()` descends into nested models:

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

The default values of nested models therefore appear in the output (`role`, `birth_date`).

## Arrays

An `array` property accepts an array **without constraining its contents**: PHP has no generics.

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

To validate **the type of the elements**, use a `TypeAdapter` (see [Types and annotations](typing.md)):

```php
use Svinx\Validator\TypeAdapter;
use Svinx\Validator\Typing\Type;
use Svinx\Validator\Validators\EmailStr;

$emails = TypeAdapter::of(Type::list(EmailStr::class));
$validated = $emails->validate(['a@b.co', 'c@d.co']);   // EmailStr[]
```

The `model_dump()` of a model containing models or validated types inside an array stays recursive:

```php
class Basket extends BaseModel
{
    public array $items = [];    // contenu libre
}

$basket->model_dump();   // les BaseModel et types validés présents sont sérialisés
```

## Models and `extra`

By default unknown keys are ignored at every level. To forbid them at the model level, see [Configuration](configuration.md#extra).

## Summary

| Declaration | Behavior |
| --- | --- |
| `public User $author;` | required, accepts `array` or `User` |
| `public ?User $author = null;` | optional |
| `public array $tags = [];` | free array, no checks on the elements |
| `public Post $post;` | recursive nesting (as many levels as needed) |

Next: [validated types](validated-types.md).
