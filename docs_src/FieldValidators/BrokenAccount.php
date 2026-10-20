<?php

namespace Svinx\Validator\Examples\FieldValidators;

use Svinx\Validator\BaseModel;
use Svinx\Validator\FieldValidator;

class BrokenAccount extends BaseModel
{
    public string $name = '';

    #[FieldValidator('champ_inexistant')]
    public function validate_ghost(string $cls, mixed $value): mixed
    {
        return $value;
    }
}
