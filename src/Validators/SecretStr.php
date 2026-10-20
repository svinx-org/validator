<?php

namespace Svinx\Validator\Validators;

/**
 * Chaîne secrète (équivalent de `SecretStr` de Pydantic).
 *
 *     public SecretStr $api_key;
 *
 * La valeur réelle n'est jamais exposée par model_dump(), JSON ou `echo` :
 * elle est masquée par `**********`. Utilisez getSecretValue() pour y accéder
 * explicitement (journalisation, appel HTTP...).
 */
class SecretStr extends AbstractType
{
    public const MASK = '**********';

    protected function parse(mixed $raw): string
    {
        return self::requireString($raw, $this->typeName());
    }

    /** Valeur en clair : à n'utiliser qu'au moment de l'usage réel. */
    public function getSecretValue(): string
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    public function dump(): string
    {
        return self::MASK;
    }

    public function __toString(): string
    {
        return self::MASK;
    }
}
