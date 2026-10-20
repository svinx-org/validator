<?php

namespace Svinx\Validator\Examples\FieldValidators;

use DateTime;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;
use Svinx\Validator\FieldValidator;
use Svinx\Validator\ValidationError;

class Account extends BaseModel
{
    #[Field(alias: 'user_id', gt: 0)]
    public int $id;

    #[Field(alias: 'username', min_length: 2)]
    public string $name = 'Anonyme';

    #[Field(alias: 'birth_date')]
    public ?DateTime $birthday = null;

    public string $nickname = '';

    public int $age = 0;

    // Cible par alias, retour utilisé pour transformer la valeur.
    #[FieldValidator('username')]
    public function validate_username(string $cls, string $value): string
    {
        if (str_contains($value, ' ')) {
            throw new ValidationError("Le username ne doit pas contenir d'espace.");
        }

        return strtoupper($value);
    }

    // Cible déduite du nom de méthode (validate_<champ>) + mode before.
    #[FieldValidator(mode: FieldValidator::BEFORE)]
    public function validate_birth_date(string $cls, mixed $value): mixed
    {
        return is_string($value) ? new DateTime($value) : $value;
    }

    #[FieldValidator(['nickname', 'age'])]
    public function validate_multi(string $cls, mixed $value): mixed
    {
        return is_int($value) ? $value * 2 : strtoupper($value);
    }

    #[FieldValidator(FieldValidator::ANY)]
    public function validate_everything(string $cls, mixed $value): mixed
    {
        return $value;
    }
}
