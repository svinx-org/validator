<?php

namespace Svinx\Validator\Validators;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Horodatage ISO 8601 (équivalent de `datetime` de Pydantic).
 *
 *     public Datetime $created_at;
 *
 * Parsing strict, plus prévisible que `new \DateTime()` : sont acceptés
 *  - une date seule (`AAAA-MM-JJ`, minuit) ;
 *  - `AAAA-MM-JJ HH:MM` ou `AAAA-MM-JJTHH:MM` ;
 *  - les secondes et les fractions de seconde facultatives ;
 *  - un fuseau facultatif (`Z`, `+02:00`, `+0200`) ; à défaut, le fuseau
 *    par défaut de PHP est utilisé ;
 *  - toute instance de \DateTimeInterface.
 *
 * La valeur interne est un \DateTimeImmutable ; dump() renvoie un ISO 8601
 * avec fuseau (`DATE_ATOM`, microsecondes si présentes).
 *
 * Attention : PHP étant insensible à la casse sur les noms de classes, importer
 * cette classe (`use Svinx\Validator\Validators\Datetime;`) masque le `\DateTime`
 * natif dans le même fichier. Écrivez alors `\DateTime` complètement qualifié,
 * ou alias : `use Svinx\Validator\Validators\Datetime as IsoDatetime;`.
 */
class Datetime extends AbstractType
{
    private const PATTERN =
        '/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2})(?:\.(\d{1,6}))?)?)?(Z|[+-]\d{2}:?\d{2})?$/i';

    protected function parse(mixed $raw): DateTimeImmutable
    {
        if ($raw instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($raw);
        }

        $raw = trim(self::requireString($raw, $this->typeName()));
        $matches = [];

        if (preg_match(self::PATTERN, $raw, $matches) !== 1) {
            self::fail(sprintf(
                'Datetime invalide : %s ne respecte pas le format ISO 8601 (AAAA-MM-JJTHH:MM:SS).',
                var_export($raw, true)
            ));
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        $hour = (int) ($matches[4] ?? 0);
        $minute = (int) ($matches[5] ?? 0);
        $second = (int) ($matches[6] ?? 0);
        $micro = (int) str_pad($matches[7] ?? '', 6, '0');
        $offset = $matches[8] ?? '';

        if (!checkdate($month, $day, $year)) {
            self::fail(sprintf('Datetime invalide : %s n\'est pas une date du calendrier.', var_export($raw, true)));
        }

        if ($hour > 23 || $minute > 59 || $second > 59) {
            self::fail(sprintf('Datetime invalide : %s contient une heure impossible.', var_export($raw, true)));
        }

        $timezone = $this->timezone($offset);

        return (new DateTimeImmutable('now', $timezone))
            ->setDate($year, $month, $day)
            ->setTime($hour, $minute, $second, $micro);
    }

    private function timezone(string $offset): DateTimeZone
    {
        if ($offset === '') {
            return new DateTimeZone(date_default_timezone_get());
        }

        $offset = strtoupper($offset) === 'Z' ? '+00:00' : $offset;

        if (strlen($offset) === 5) {
            $offset = substr($offset, 0, 3) . ':' . substr($offset, 3);
        }

        return new DateTimeZone($offset);
    }

    public function dump(): string
    {
        return (int) $this->value->format('u') === 0
            ? $this->value->format(DATE_ATOM)
            : $this->value->format('Y-m-d\TH:i:s.uP');
    }

    /** Valeur interne en \DateTimeImmutable. */
    public function toDateTime(): DateTimeImmutable
    {
        return $this->value;
    }

    /** Convertit l'horodatage dans un autre fuseau. */
    public function toTimezone(string $timezone): static
    {
        return new static($this->value->setTimezone(new DateTimeZone($timezone)));
    }

    /** Date seule correspondante. */
    public function toDate(): Date
    {
        return new Date($this->value);
    }
}
