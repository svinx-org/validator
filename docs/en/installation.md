# Installation

## Requirements

- **PHP 8.4 or higher** (attributes, `readonly`, `never`, `array_is_list`, constructor promotion);
- **Composer**;
- the `mbstring` extension (used for string lengths) and `json` (both shipped with PHP by default).

```bash
php -v
# PHP 8.4.x
```

## Installing the package

```bash
composer require svinx/validator
```

!!! tip "Local development"
    The package is not published on Packagist yet. To work on a copy of the repository, declare a `path` repository:

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

Then run `composer update svinx/validator`.

## Quick check

Create a `demo.php` file:

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

If you see `string(2) "ok"`, everything is in place.

## The examples in this course

All the examples live in the [`docs_src/`](https://github.com/svinx-org/validator/tree/main/docs_src) folder and can be autoloaded through the `Svinx\Validator\Examples` namespace:

```php
use Svinx\Validator\Examples\Models\User;
use Svinx\Validator\Examples\Validators\Customer;
```

They are **exactly** the classes used by the test suite: the documentation and the tests cannot drift apart.

Every snippet names the corresponding file:

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

## Running the tests

```bash
composer install
composer test          # phpunit
```

The suite covers the validator core, the validated types, typing, configuration and settings.
