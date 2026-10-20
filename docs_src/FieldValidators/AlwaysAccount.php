<?php

namespace Svinx\Validator\Examples\FieldValidators;

use Svinx\Validator\BaseModel;
use Svinx\Validator\FieldValidator;

class AlwaysAccount extends BaseModel
{
    public ?string $note = null;

    #[FieldValidator('note', always: true)]
    public function check_note(string $cls, ?string $value): string
    {
        return $value ?? 'défaut';
    }
}
