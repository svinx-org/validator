<?php

namespace Svinx\Validator;

/**
 * Représente les métadonnées d'un champ, à l'image de FieldInfo de Pydantic.
 */
class FieldInfo
{
    /**
     * @param mixed $default Valeur par défaut (si non fournie, le champ est requis)
     * @param callable|null $default_factory Callable retournant la valeur par défaut
     * @param string|null $alias Nom alternatif pour le champ (ex. pour la sérialisation)
     * @param string|null $title Titre descriptif
     * @param string|null $description Description détaillée
     * @param float|int|null $gt Valeur strictement supérieure à (numérique)
     * @param float|int|null $ge Valeur supérieure ou égale à
     * @param float|int|null $lt Valeur strictement inférieure à
     * @param float|int|null $le Valeur inférieure ou égale à
     * @param float|int|null $multiple_of Le nombre doit être un multiple de cette valeur
     * @param int|null $min_length Longueur minimale (chaîne, liste)
     * @param int|null $max_length Longueur maximale
     * @param string|null $pattern Expression régulière (pour chaîne)
     * @param array|null $choices Liste des valeurs autorisées
     * @param array $extra Métadonnées supplémentaires libres
     */
    public function __construct(
        public mixed $default = null,
        public $default_factory = null,
        public ?string $alias = null,
        public ?string $title = null,
        public ?string $description = null,
        public float|int|null $gt = null,
        public float|int|null $ge = null,
        public float|int|null $lt = null,
        public float|int|null $le = null,
        public float|int|null $multiple_of = null,
        public ?int $min_length = null,
        public ?int $max_length = null,
        public ?string $pattern = null,
        public ?array $choices = null,
        public array $extra = [],
    ) {
        // Validation simple des contraintes (incohérences logiques)
        if ($this->gt !== null && $this->ge !== null) {
            throw new \InvalidArgumentException('gt et ge ne peuvent pas être définis simultanément');
        }
        if ($this->lt !== null && $this->le !== null) {
            throw new \InvalidArgumentException('lt et le ne peuvent pas être définis simultanément');
        }
        if ($this->gt !== null && $this->lt !== null && $this->gt >= $this->lt) {
            throw new \InvalidArgumentException('gt doit être inférieur à lt');
        }
        if ($this->ge !== null && $this->le !== null && $this->ge > $this->le) {
            throw new \InvalidArgumentException('ge doit être inférieur ou égal à le');
        }
        if ($this->min_length !== null && $this->max_length !== null && $this->min_length > $this->max_length) {
            throw new \InvalidArgumentException('min_length ne peut pas dépasser max_length');
        }
    }

    /**
     * Détermine si le champ est requis (pas de valeur par défaut ni de factory).
     */
    public function isRequired(): bool
    {
        return $this->default === null && $this->default_factory === null;
    }

    /**
     * Vérifie les contraintes sur une valeur brute (avant conversion de type).
     *
     * @throws \Svinx\Validator\ValidationError si une contrainte n'est pas respectée.
     */
    public function check(mixed $value, string $name = 'La valeur'): void
    {
        if (is_numeric($value)) {
            if ($this->gt !== null && !($value > $this->gt)) {
                throw new ValidationError("$name doit être > {$this->gt}");
            }
            if ($this->ge !== null && !($value >= $this->ge)) {
                throw new ValidationError("$name doit être >= {$this->ge}");
            }
            if ($this->lt !== null && !($value < $this->lt)) {
                throw new ValidationError("$name doit être < {$this->lt}");
            }
            if ($this->le !== null && !($value <= $this->le)) {
                throw new ValidationError("$name doit être <= {$this->le}");
            }
            if ($this->multiple_of !== null && fmod((float) $value, (float) $this->multiple_of) != 0) {
                throw new ValidationError("$name doit être multiple de {$this->multiple_of}");
            }
        }

        if (is_string($value)) {
            if ($this->min_length !== null && mb_strlen($value) < $this->min_length) {
                throw new ValidationError("$name doit avoir au moins {$this->min_length} caractères");
            }
            if ($this->max_length !== null && mb_strlen($value) > $this->max_length) {
                throw new ValidationError("$name ne doit pas dépasser {$this->max_length} caractères");
            }
            if ($this->pattern !== null && !preg_match($this->pattern, $value)) {
                throw new ValidationError("$name ne correspond pas au pattern attendu");
            }
        }

        if ($this->choices !== null && !in_array($value, $this->choices, true)) {
            throw new ValidationError(
                "$name doit être une des valeurs : " . implode(', ', array_map(
                    static fn (mixed $choice) => is_scalar($choice) ? (string) $choice : json_encode($choice),
                    $this->choices
                ))
            );
        }
    }

    /**
     * Contraintes au format JSON Schema.
     *
     * @return array<string, mixed>
     */
    public function jsonSchema(): array
    {
        $schema = [];

        if ($this->title !== null) {
            $schema['title'] = $this->title;
        }
        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }
        if ($this->gt !== null) {
            $schema['exclusiveMinimum'] = $this->gt;
        }
        if ($this->ge !== null) {
            $schema['minimum'] = $this->ge;
        }
        if ($this->lt !== null) {
            $schema['exclusiveMaximum'] = $this->lt;
        }
        if ($this->le !== null) {
            $schema['maximum'] = $this->le;
        }
        if ($this->multiple_of !== null) {
            $schema['multipleOf'] = $this->multiple_of;
        }
        if ($this->min_length !== null) {
            $schema['minLength'] = $this->min_length;
        }
        if ($this->max_length !== null) {
            $schema['maxLength'] = $this->max_length;
        }
        if ($this->pattern !== null) {
            $schema['pattern'] = $this->pattern;
        }
        if ($this->choices !== null) {
            $schema['enum'] = $this->choices;
        }
        if ($this->default !== null) {
            $schema['default'] = $this->default;
        }

        return $schema;
    }

    /**
     * Récupère la valeur par défaut (en exécutant la factory si présente).
     */
    public function getDefault(): mixed
    {
        if ($this->default_factory !== null) {
            return ($this->default_factory)();
        }
        return $this->default;
    }

    /**
     * Méthode utilitaire pour créer une instance avec une syntaxe fluide.
     */
    public static function make(
        mixed $default = null,
        ?callable $default_factory = null,
        ?string $alias = null,
        ?string $title = null,
        ?string $description = null,
        float|int|null $gt = null,
        float|int|null $ge = null,
        float|int|null $lt = null,
        float|int|null $le = null,
        float|int|null $multiple_of = null,
        ?int $min_length = null,
        ?int $max_length = null,
        ?string $pattern = null,
        ?array $choices = null,
        array $extra = [],
    ): self {
        return new self(
            default: $default,
            default_factory: $default_factory,
            alias: $alias,
            title: $title,
            description: $description,
            gt: $gt,
            ge: $ge,
            lt: $lt,
            le: $le,
            multiple_of: $multiple_of,
            min_length: $min_length,
            max_length: $max_length,
            pattern: $pattern,
            choices: $choices,
            extra: $extra,
        );
    }
}