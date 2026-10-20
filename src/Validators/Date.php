<?php

namespace Svinx\Validator\Validators;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Date sans heure (équivalent de `date` de Pydantic).
 *
 *     public Date $birthday;
 *
 * Parsing strict : seules la forme ISO `AAAA-MM-JJ` et les instances de
 * \DateTimeInterface sont acceptées (contrairement à `new \DateTime()` qui
 * devine des formats très variés). La valeur interne est un
 * \DateTimeImmutable à minuit ; dump() renvoie `AAAA-MM-JJ`.
 */
class Date extends AbstractType
{
    protected function parse(mixed $raw): DateTimeImmutable
    {
        if ($raw instanceof DateTimeInterface) {
            return new DateTimeImmutable($raw->format('Y-m-d') . ' 00:00:00', $raw->getTimezone());
        }

        $raw = self::requireString($raw, $this->typeName());
        $raw = trim($raw);

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

        if ($date === false || $date->format('Y-m-d') !== $raw) {
            self::fail(sprintf(
                'Date invalide : %s n\'est pas une date valide au format AAAA-MM-JJ.',
                var_export($raw, true)
            ));
        }

        return $date;
    }

    public function dump(): string
    {
        return $this->value->format('Y-m-d');
    }

    /** Valeur interne en \DateTimeImmutable (à minuit). */
    public function toDateTime(): DateTimeImmutable
    {
        return $this->value;
    }

    public function year(): int
    {
        return (int) $this->value->format('Y');
    }

    public function month(): int
    {
        return (int) $this->value->format('n');
    }

    public function day(): int
    {
        return (int) $this->value->format('j');
    }
}
