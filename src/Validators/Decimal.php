<?php

namespace Svinx\Validator\Validators;

use Stringable;

/**
 * Nombre décimal à précision exacte (équivalent de `Decimal` de Pydantic).
 *
 *     public Decimal $price;
 *
 * La valeur est stockée sous forme de chaîne décimale canonique : les entrées
 * textuelles et entières conservent leur précision exacte (`'0.1'` reste
 * `0.1`, `'1.50'` devient `1.5`), et la valeur n'est jamais arrondie en float
 * tant que toFloat() n'est pas appelé explicitement. Un float d'entrée, lui,
 * ne peut pas retrouver une précision déjà perdue par IEEE 754.
 * Accepte int, float, chaîne numérique (notation décimale ou scientifique)
 * et Decimal.
 */
class Decimal extends AbstractType
{
    protected function parse(mixed $raw): string
    {
        return self::canonicalize(self::asNumericString($raw));
    }

    /** Valeur convertie en float (perte de précision possible). */
    public function toFloat(): float
    {
        return (float) $this->value;
    }

    /**
     * Valeur convertie en entier.
     *
     * @throws \Svinx\Validator\ValidationError si la valeur n'est pas entière.
     */
    public function toInt(): int
    {
        if (!$this->isInteger()) {
            AbstractType::fail(sprintf('Decimal %s ne peut pas être converti en entier sans perte.', $this->value));
        }

        return (int) $this->value;
    }

    public function isInteger(): bool
    {
        return !str_contains($this->value, '.');
    }

    public function isNegative(): bool
    {
        return $this->value[0] === '-';
    }

    /** Comparaison exacte : -1, 0 ou 1. */
    public function compare(mixed $other): int
    {
        $a = $this->value;
        $b = $other instanceof self ? $other->value : self::canonicalize(self::asNumericString($other));

        $signA = $a[0] === '-' ? -1 : 1;
        $signB = $b[0] === '-' ? -1 : 1;

        if ($signA !== $signB) {
            return $signA <=> $signB;
        }

        return $signA * self::compareAbsolute(ltrim($a, '-'), ltrim($b, '-'));
    }

    public function equals(mixed $other): bool
    {
        return $this->compare($other) === 0;
    }

    // ------------------------------------------------------------------

    private static function asNumericString(mixed $raw): string
    {
        if ($raw instanceof self) {
            return $raw->value;
        }

        if (is_int($raw)) {
            return (string) $raw;
        }

        if (is_float($raw)) {
            if (!is_finite($raw)) {
                AbstractType::fail('Decimal invalide : la valeur doit être un nombre fini.');
            }

            // var_export() fournit la représentation la plus courte exacte.
            return var_export($raw, true);
        }

        if (is_string($raw)) {
            return trim($raw);
        }

        if ($raw instanceof Stringable) {
            return trim((string) $raw);
        }

        AbstractType::fail(sprintf(
            'Decimal doit être un int, un float ou une chaîne numérique, %s donné.',
            get_debug_type($raw)
        ));
    }

    /**
     * Normalise un nombre en chaîne décimale canonique (sans exposant,
     * sans zéros superflus, sans signe inutile).
     */
    private static function canonicalize(string $value): string
    {
        $matches = [];

        if (preg_match('/^([+-]?)(?:(\d+)(?:\.(\d*))?|\.(\d+))(?:[eE]([+-]?\d+))?$/', $value, $matches) !== 1) {
            AbstractType::fail(sprintf('Decimal invalide : %s n\'est pas un nombre décimal valide.', var_export($value, true)));
        }

        $negative = ($matches[1] ?? '') === '-';

        if (($matches[2] ?? '') !== '') {
            $integer = $matches[2];
            $fraction = $matches[3] ?? '';
        } else {
            $integer = '0';
            $fraction = $matches[4] ?? '';
        }

        $exponent = ($matches[5] ?? '') !== '' ? (int) $matches[5] : 0;

        [$integer, $fraction] = self::applyExponent($integer, $fraction, $exponent);

        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = rtrim($fraction, '0');

        $result = $fraction === '' ? $integer : $integer . '.' . $fraction;

        if ($result === '0') {
            return '0';
        }

        return ($negative ? '-' : '') . $result;
    }

    /**
     * @return array{0: string, 1: string} [partie entière, partie décimale]
     */
    private static function applyExponent(string $integer, string $fraction, int $exponent): array
    {
        if ($exponent > 0) {
            if (strlen($fraction) >= $exponent) {
                $integer .= substr($fraction, 0, $exponent);
                $fraction = substr($fraction, $exponent);
            } else {
                $integer .= $fraction . str_repeat('0', $exponent - strlen($fraction));
                $fraction = '';
            }

            return [$integer, $fraction];
        }

        if ($exponent < 0) {
            $shift = -$exponent;

            if (strlen($integer) > $shift) {
                $fraction = substr($integer, -$shift) . $fraction;
                $integer = substr($integer, 0, -$shift);
            } else {
                $fraction = str_repeat('0', $shift - strlen($integer)) . $integer . $fraction;
                $integer = '0';
            }
        }

        return [$integer, $fraction];
    }

    private static function compareAbsolute(string $a, string $b): int
    {
        [$intA, $fracA] = array_pad(explode('.', $a, 2), 2, '');
        [$intB, $fracB] = array_pad(explode('.', $b, 2), 2, '');

        if (strlen($intA) !== strlen($intB)) {
            return strlen($intA) <=> strlen($intB);
        }

        $cmp = strcmp($intA, $intB);
        if ($cmp !== 0) {
            return $cmp <=> 0;
        }

        $length = max(strlen($fracA), strlen($fracB));
        $cmp = strcmp(str_pad($fracA, $length, '0'), str_pad($fracB, $length, '0'));

        return $cmp <=> 0;
    }
}
