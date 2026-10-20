<?php

namespace Svinx\Validator;

use InvalidArgumentException;

/**
 * Configuration d'un modèle, équivalent de `model_config = ConfigDict(...)` de
 * Pydantic.
 *
 *     class User extends BaseModel
 *     {
 *         public static function model_config(): Config
 *         {
 *             return new Config(extra: Config::EXTRA_FORBID, str_strip_whitespace: true);
 *         }
 *     }
 *
 * Options disponibles :
 *  - extra                : EXTRA_IGNORE (défaut), EXTRA_FORBID, EXTRA_ALLOW ;
 *  - populate_by_name     : accepte le nom de propriété en plus de l'alias ;
 *  - alias_generator      : callable(string $property): string ;
 *  - str_strip_whitespace : retire les espaces autour des chaînes ;
 *  - str_to_lower         / str_to_upper : normalise la casse des chaînes ;
 *  - title                : titre du modèle (utilisé par model_schema()).
 */
class Config
{
    public const EXTRA_IGNORE = 'ignore';
    public const EXTRA_FORBID = 'forbid';
    public const EXTRA_ALLOW = 'allow';

    public readonly mixed $alias_generator;

    public function __construct(
        public readonly string $extra = self::EXTRA_IGNORE,
        public readonly bool $populate_by_name = true,
        mixed $alias_generator = null,
        public readonly bool $str_strip_whitespace = false,
        public readonly bool $str_to_lower = false,
        public readonly bool $str_to_upper = false,
        public readonly ?string $title = null,
    ) {
        if (!in_array($extra, [self::EXTRA_IGNORE, self::EXTRA_FORBID, self::EXTRA_ALLOW], true)) {
            throw new InvalidArgumentException(sprintf(
                'Option extra inconnue : "%s" (attendu : ignore, forbid ou allow).',
                $extra
            ));
        }

        if ($alias_generator !== null && !is_callable($alias_generator)) {
            throw new InvalidArgumentException('alias_generator doit être callable ou null.');
        }

        $this->alias_generator = $alias_generator;
    }

    /**
     * Fabrique avec surcharge partielle des options.
     *
     * @param array<string, mixed> $options
     */
    public static function make(array $options = []): static
    {
        $defaults = [
            'extra' => self::EXTRA_IGNORE,
            'populate_by_name' => true,
            'alias_generator' => null,
            'str_strip_whitespace' => false,
            'str_to_lower' => false,
            'str_to_upper' => false,
            'title' => null,
        ];

        $unknown = array_diff_key($options, $defaults);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Options de configuration inconnues : ' . implode(', ', array_keys($unknown)));
        }

        return new static(...array_merge($defaults, $options));
    }

    /**
     * Copie la configuration en changeant certaines options.
     *
     * @param array<string, mixed> $changes
     */
    public function with(array $changes): static
    {
        return static::make([...$this->toArray(), ...$changes]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'extra' => $this->extra,
            'populate_by_name' => $this->populate_by_name,
            'alias_generator' => $this->alias_generator,
            'str_strip_whitespace' => $this->str_strip_whitespace,
            'str_to_lower' => $this->str_to_lower,
            'str_to_upper' => $this->str_to_upper,
            'title' => $this->title,
        ];
    }

    /**
     * Alias effectif d'une propriété (Field(alias:) prioritaire, sinon générateur).
     */
    public function aliasFor(string $property, ?string $alias): ?string
    {
        if ($alias !== null) {
            return $alias;
        }

        if ($this->alias_generator !== null) {
            return (string) ($this->alias_generator)($property);
        }

        return null;
    }

    /**
     * Normalise une valeur textuelle brute selon la configuration.
     */
    public function normalizeString(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        if ($this->str_strip_whitespace) {
            $value = trim($value);
        }

        if ($this->str_to_lower) {
            $value = mb_strtolower($value);
        }

        if ($this->str_to_upper) {
            $value = mb_strtoupper($value);
        }

        return $value;
    }
}
