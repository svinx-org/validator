<?php

namespace Svinx\Validator\Validators;

/**
 * Identifiant UUID valide (équivalent de `UUID` de Pydantic).
 *
 *     #[Field(alias: 'id')]
 *     public UUID $id;
 *
 * Accepte la forme canonique (`xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`),
 * la forme sans tirets, la forme entre accolades et tout objet Stringable
 * (ex. `Ramsey\Uuid\Uuid`). La valeur est normalisée en minuscules canoniques.
 */
class UUID extends AbstractType
{
    private const HYPHENATED = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    private const COMPACT = '/^[0-9a-f]{32}$/i';

    protected function parse(mixed $raw): string
    {
        $uuid = trim(self::requireString($raw, $this->typeName()));
        $uuid = trim($uuid, '{}');

        if (preg_match(self::COMPACT, $uuid) === 1) {
            $uuid = sprintf(
                '%s-%s-%s-%s-%s',
                substr($uuid, 0, 8),
                substr($uuid, 8, 4),
                substr($uuid, 12, 4),
                substr($uuid, 16, 4),
                substr($uuid, 20, 12)
            );
        } elseif (preg_match(self::HYPHENATED, $uuid) !== 1) {
            self::fail(sprintf('UUID invalide : %s n\'est pas un UUID valide.', var_export($uuid, true)));
        }

        return strtolower($uuid);
    }

    /** Version de l'UUID (1 à 8), déduite du premier chiffre du 3e groupe. */
    public function version(): int
    {
        return (int) hexdec($this->value[14]);
    }

    /** Variante RFC 4122 (8, 9, a ou b => n ou 2). */
    public function variant(): string
    {
        return match (strtolower($this->value[19])) {
            '8', '9', 'a', 'b' => 'RFC 4122',
            'c', 'd' => 'Microsoft',
            'e' => 'Future',
            default => 'Reserved',
        };
    }

    /** Représentation binaire (16 octets) de l'UUID. */
    public function toBinary(): string
    {
        return (string) hex2bin(str_replace('-', '', $this->value));
    }

    /** Représentation compacte, sans tirets. */
    public function toCompact(): string
    {
        return str_replace('-', '', $this->value);
    }
}
