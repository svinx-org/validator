<?php

namespace Svinx\Validator\Validators;

/**
 * Adresse email valide (équivalent de `EmailStr` de Pydantic).
 *
 *     #[Field(alias: 'email')]
 *     public EmailStr $email;
 *
 * Les espaces de début/fin sont retirés ; la casse locale n'est pas modifiée.
 */
class EmailStr extends AbstractType
{
    public const MAX_LENGTH = 254;

    protected function parse(mixed $raw): string
    {
        $email = trim(self::requireString($raw, $this->typeName()));

        if (strlen($email) > self::MAX_LENGTH) {
            self::fail(sprintf(
                'EmailStr invalide : %d caractères maximum, %d donné.',
                self::MAX_LENGTH,
                strlen($email)
            ));
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            self::fail(sprintf('EmailStr invalide : %s n\'est pas une adresse email valide.', var_export($email, true)));
        }

        return $email;
    }

    public function local(): string
    {
        return (string) strstr($this->value, '@', true);
    }

    public function domain(): string
    {
        return (string) substr((string) strrchr($this->value, '@'), 1);
    }
}
