<?php

namespace Svinx\Validator\Examples\FieldValidators;

use Svinx\Validator\BaseModel;
use Svinx\Validator\FieldValidator;
use Svinx\Validator\ValidationError;

class StrictAccount extends BaseModel
{
    public string $code = 'ok';

    public ?string $note = null;

    #[FieldValidator('code')]
    public function check_code(string $cls, string $value): void
    {
        if ($value !== 'ok') {
            throw new ValidationError('Code invalide.');
        }
    }

    // Ne doit jamais s'exécuter tant que la valeur est null.
    #[FieldValidator('note')]
    public function check_note(string $cls, string $value): string
    {
        return $value . '!';
    }
}
