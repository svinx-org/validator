# Installation

## Prérequis

- **PHP 8.4 ou supérieur** (attributs, `readonly`, `never`, `array_is_list`, promotion de constructeur) ;
- **Composer** ;
- l'extension `mbstring` (utilisée pour les longueurs de chaînes) et `json` (fournies par défaut avec PHP).

```bash
php -v
# PHP 8.4.x
```

## Installation du paquet

```bash
composer require svinx/validator
```

!!! tip "Développement local"
    Le paquet n'est pas encore publié sur Packagist. Pour travailler sur une copie du dépôt, déclarez un dépôt de type `path` :

    ```json
    {
        "repositories": [
            { "type": "path", "url": "../svinx-validator" }
        ],
        "require": {
            "svinx/validator": "*"
        }
    }
    ```

Puis `composer update svinx/validator`.

## Vérification rapide

Créez un fichier `demo.php` :

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

class Ping extends BaseModel
{
    #[Field(min_length: 2)]
    public string $message;
}

var_dump((new Ping(['message' => 'ok']))->message);
```

```bash
php demo.php
# string(2) "ok"
```

Si vous voyez `string(2) "ok"`, tout est en place.

## Les exemples de ce cours

Tous les exemples vivent dans le dossier [`docs_src/`](https://github.com/svinx-org/validator/tree/main/docs_src) et sont chargeables via le namespace `Svinx\Validator\Examples` :

```php
use Svinx\Validator\Examples\Models\User;
use Svinx\Validator\Examples\Validators\Customer;
```

Ce sont **exactement** les classes utilisées par la suite de tests : la documentation et les tests ne peuvent pas diverger.

Chaque snippet indique le fichier correspondant :

!!! example "docs_src/Models/User.php"
    ```php
    <?php

    namespace Svinx\Validator\Examples\Models;

    use Svinx\Validator\BaseModel;
    use Svinx\Validator\Field;

    class User extends BaseModel
    {
        // ...
    }
    ```

## Lancer les tests

```bash
composer install
composer test          # phpunit
```

La suite couvre le cœur du validateur, les types validés, le typage, la configuration et les settings.
