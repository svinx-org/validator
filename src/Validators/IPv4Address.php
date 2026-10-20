<?php

namespace Svinx\Validator\Validators;

/**
 * Adresse IPv4 valide (équivalent de `IPv4Address` de Pydantic).
 *
 *     public IPv4Address $ip;
 *
 * La valeur est normalisée en notation pointée canonique (inet_ntop).
 */
class IPv4Address extends AbstractType
{
    protected function parse(mixed $raw): string
    {
        $ip = trim(self::requireString($raw, $this->typeName()));

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            self::fail(sprintf('IPv4Address invalide : %s n\'est pas une adresse IPv4 valide.', var_export($ip, true)));
        }

        return self::canonicalize($ip);
    }

    /** Représentation binaire (4 octets) de l'adresse. */
    public function toBinary(): string
    {
        return (string) inet_pton($this->value);
    }

    /** Représentation entière non signée de l'adresse. */
    public function toInt(): int
    {
        return (int) sprintf('%u', ip2long($this->value));
    }

    protected static function canonicalize(string $ip): string
    {
        $binary = @inet_pton($ip);

        return $binary === false ? $ip : (string) inet_ntop($binary);
    }
}
