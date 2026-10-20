<?php

namespace Svinx\Validator\Examples\FieldValidators;

use Svinx\Validator\BaseModel;
use Svinx\Validator\FieldValidator;

class CoercingAccount extends BaseModel
{
    public string $name = '';

    #[FieldValidator('name')]
    public function validate_name(string $cls, mixed $value): mixed
    {
        return ['invalide'];
    }
}
