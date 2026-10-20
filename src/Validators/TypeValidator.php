<?php

namespace Svinx\Validator\Validators;

/**
 * Contrat commun des types validés (équivalent des types de Pydantic :
 * EmailStr, HttpUrl, UUID, Decimal, Date, Datetime, IPv4Address, IPv6Address).
 *
 * Un type validé est un objet-valeur immuable :
 *  - sa construction valide et convertit la valeur brute ;
 *  - value() expose la valeur PHP normalisée ;
 *  - dump() expose la valeur sérialisable utilisée par BaseModel::model_dump().
 */
interface TypeValidator
{
    /**
     * Convertit une valeur brute en instance du type.
     * Lève une ValidationError si la valeur est invalide.
     */
    public static function validate(mixed $raw): static;

    /** Valeur PHP normalisée (string, int, \DateTimeImmutable, ...). */
    public function value(): mixed;

    /** Valeur sérialisable (celle exposée par model_dump()). */
    public function dump(): mixed;
}
