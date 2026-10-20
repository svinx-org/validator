<?php

namespace Svinx\Validator\Validators;

use JsonSerializable;
use Svinx\Validator\ValidationError;
use Stringable;

/**
 * Base commune des types validés.
 *
 * Les sous-classes n'implémentent que parse() : la validation, la conversion
 * et la sérialisation sont mutualisées ici.
 */
abstract class AbstractType implements TypeValidator, JsonSerializable, Stringable
{
    protected mixed $value;

    public function __construct(mixed $raw)
    {
        // Un type validé en entrée est déballé : new Date($datetime) fonctionne.
        $this->value = $this->parse($raw instanceof TypeValidator ? $raw->value() : $raw);
    }

    /**
     * Valide la valeur brute et retourne sa représentation interne normalisée.
     *
     * @throws ValidationError si la valeur ne respecte pas le format attendu.
     */
    abstract protected function parse(mixed $raw): mixed;

    public static function validate(mixed $raw): static
    {
        return $raw instanceof static ? $raw : new static($raw);
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function dump(): mixed
    {
        return $this->value;
    }

    public function jsonSerialize(): mixed
    {
        return $this->dump();
    }

    public function __toString(): string
    {
        $value = $this->dump();

        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value);
    }

    /**
     * Vérifie deux instances du même type par valeur normalisée.
     */
    public function equals(mixed $other): bool
    {
        return $other instanceof static && $other->value() === $this->value();
    }

    // ------------------------------------------------------------------
    // Aides pour les sous-classes
    // ------------------------------------------------------------------

    /**
     * Nom lisible du type, utilisé dans les messages d'erreur.
     */
    protected function typeName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * Exige une chaîne (ou un objet Stringable) et la retourne.
     */
    protected static function requireString(mixed $raw, string $type): string
    {
        if ($raw instanceof Stringable) {
            return (string) $raw;
        }

        if (!is_string($raw)) {
            self::fail(sprintf('%s doit être une chaîne de caractères, %s donné.', $type, get_debug_type($raw)));
        }

        return $raw;
    }

    protected static function fail(string $message): never
    {
        throw new ValidationError($message);
    }
}
