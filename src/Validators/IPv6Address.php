<?php

namespace Svinx\Validator\Validators;

/**
 * Adresse IPv6 valide (équivalent de `IPv6Address` de Pydantic).
 *
 *     public IPv6Address $ip;
 *
 * La valeur est normalisée en notation compressée minuscule (inet_ntop),
 * par exemple `2001:0DB8:0000:0000:0000:0000:0000:0001` => `2001:db8::1`.
 */
class IPv6Address extends AbstractType
{
    protected function parse(mixed $raw): string
    {
        $ip = trim(self::requireString($raw, $this->typeName()));

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            self::fail(sprintf('IPv6Address invalide : %s n\'est pas une adresse IPv6 valide.', var_export($ip, true)));
        }

        $binary = @inet_pton($ip);

        return $binary === false ? strtolower($ip) : (string) inet_ntop($binary);
    }

    /** Représentation binaire (16 octets) de l'adresse. */
    public function toBinary(): string
    {
        return (string) inet_pton($this->value);
    }

    /** Adresse IPv4 encapsulée (::ffff:a.b.c.d), ou null. */
    public function toIPv4(): ?IPv4Address
    {
        $binary = inet_pton($this->value);

        if ($binary === false || substr($binary, 0, 12) !== str_repeat("\0", 10) . "\xff\xff") {
            return null;
        }

        return new IPv4Address((string) inet_ntop(substr($binary, 12)));
    }
}
