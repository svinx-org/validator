<?php

namespace Svinx\Validator\Typing;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Svinx\Validator\BaseModel;
use Svinx\Validator\FieldInfo;
use Svinx\Validator\ValidationError;
use Svinx\Validator\Validators\TypeValidator;
use UnitEnum;

/**
 * Descripteur de type, équivalent des annotations de type de Pydantic
 * (`list[int]`, `Optional[str]`, `Literal['a', 'b']`, `Annotated[...]`...).
 *
 * PHP n'ayant pas de génériques, les types composés sont décrits par des objets :
 *
 *     Type::int();                              // int
 *     Type::list(Type::str());                  // list[string]
 *     Type::map(Type::str(), Type::int());      // map[string, int]
 *     Type::optional(Type::of(EmailStr::class)); // EmailStr | null
 *     Type::literal('a', 'b');                  // Literal['a', 'b']
 *     Type::of(EmailStr::class);                // type validé
 *     Type::fromReflection((new ReflectionProperty(...))->getType());
 *
 * Un Type sait valider/convertir une valeur brute (validate), produire une
 * valeur sérialisable (dump) et décrire un schéma JSON (schema).
 */
class Type
{
    public const ANY = 'any';
    public const NULL = 'null';
    public const BOOL = 'bool';
    public const INT = 'int';
    public const FLOAT = 'float';
    public const STR = 'str';
    public const ARRAY = 'array';
    public const LIST = 'list';
    public const MAP = 'map';
    public const MODEL = 'model';
    public const VALIDATOR = 'validator';
    public const OBJECT = 'object';
    public const ENUM = 'enum';
    public const LITERAL = 'literal';
    public const UNION = 'union';
    public const TYPEVAR = 'typevar';

    /**
     * @param list<Type>      $types      Membres d'une union.
     * @param list<FieldInfo> $constraints Contraintes issues d'un Annotated[...].
     * @param list<mixed>     $metadata   Métadonnées libres issues d'un Annotated[...].
     */
    public function __construct(
        public readonly string $kind,
        public readonly ?string $class = null,
        public readonly ?Type $item = null,
        public readonly ?Type $key = null,
        public readonly ?Type $value = null,
        public readonly array $types = [],
        public readonly array $literals = [],
        public readonly ?TypeVar $var = null,
        public readonly array $constraints = [],
        public readonly array $metadata = [],
        public readonly bool $coerce = false,
    ) {}

    // ------------------------------------------------------------------
    // Fabriques
    // ------------------------------------------------------------------

    public static function any(): self
    {
        return new self(self::ANY);
    }

    public static function null(): self
    {
        return new self(self::NULL);
    }

    public static function bool(): self
    {
        return new self(self::BOOL);
    }

    public static function int(): self
    {
        return new self(self::INT);
    }

    public static function float(): self
    {
        return new self(self::FLOAT);
    }

    public static function str(): self
    {
        return new self(self::STR);
    }

    public static function array(): self
    {
        return new self(self::ARRAY);
    }

    public static function list(self|string|TypeVar $item): ListType
    {
        return new ListType(self::from($item));
    }

    public static function map(self|string|TypeVar $key, self|string|TypeVar $value): Map
    {
        return new Map(self::from($key), self::from($value));
    }

    public static function union(self ...$types): self
    {
        $members = [];

        foreach ($types as $type) {
            foreach ($type->kind === self::UNION ? $type->types : [$type] as $member) {
                $members[$member->describe()] = $member;
            }
        }

        if (count($members) === 1) {
            return reset($members);
        }

        return new self(self::UNION, types: array_values($members));
    }

    public static function optional(self $type): self
    {
        return self::union($type, self::null());
    }

    public static function literal(mixed ...$values): self
    {
        return new self(self::LITERAL, literals: $values);
    }

    public static function model(string $class): self
    {
        return new self(self::MODEL, class: $class);
    }

    public static function validator(string $class): self
    {
        return new self(self::VALIDATOR, class: $class);
    }

    public static function enum(string $class): self
    {
        return new self(self::ENUM, class: $class);
    }

    public static function object(string $class): self
    {
        return new self(self::OBJECT, class: $class);
    }

    public static function typevar(TypeVar $var): self
    {
        return new self(self::TYPEVAR, var: $var);
    }

    /**
     * Construit un type à partir d'un nom de classe ou d'un type natif.
     */
    public static function of(string $classOrName): self
    {
        $normalized = strtolower(ltrim($classOrName, '\\'));

        $builtin = match ($normalized) {
            'mixed', 'any' => self::any(),
            'null' => self::null(),
            'bool', 'boolean' => self::bool(),
            'int', 'integer' => self::int(),
            'float', 'double' => self::float(),
            'string', 'str' => self::str(),
            'array', 'iterable' => self::array(),
            default => null,
        };

        if ($builtin !== null) {
            return $builtin;
        }

        $class = ltrim($classOrName, '\\');

        if (!class_exists($class) && !interface_exists($class) && !enum_exists($class)) {
            throw new ValidationError(sprintf('Type inconnu : %s.', $classOrName));
        }

        if (is_subclass_of($class, BaseModel::class)) {
            return self::model($class);
        }

        if (is_subclass_of($class, TypeValidator::class)) {
            return self::validator($class);
        }

        if (is_subclass_of($class, UnitEnum::class)) {
            return self::enum($class);
        }

        return self::object($class);
    }

    /**
     * Convertit un type quelconque (string, TypeVar, Type) en Type.
     */
    public static function from(self|string|TypeVar $type): self
    {
        if ($type instanceof self) {
            return $type;
        }

        if ($type instanceof TypeVar) {
            return self::typevar($type);
        }

        return self::of($type);
    }

    /**
     * Construit un type depuis la réflexion d'une propriété ou d'un paramètre.
     */
    public static function fromReflection(?ReflectionType $type): self
    {
        if ($type === null) {
            return self::any();
        }

        if ($type instanceof ReflectionUnionType) {
            return self::union(...array_map(self::fromReflection(...), $type->getTypes()));
        }

        if (!$type instanceof ReflectionNamedType) {
            return self::any();
        }

        $name = $type->getName();
        $result = match (strtolower($name)) {
            'mixed', 'object', 'iterable' => self::any(),
            'array' => self::array(),
            'null' => self::null(),
            default => self::of($name),
        };

        if ($type->allowsNull() && !$result->isNullable() && $result->kind !== self::ANY) {
            return self::optional($result);
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // Modificateurs (retournent une copie)
    // ------------------------------------------------------------------

    public function withConstraints(FieldInfo ...$constraints): self
    {
        return $this->copy(['constraints' => [...$this->constraints, ...$constraints]]);
    }

    public function withMetadata(mixed ...$metadata): self
    {
        return $this->copy(['metadata' => [...$this->metadata, ...$metadata]]);
    }

    /**
     * Active la coercition (mode laxiste) : "42" devient 42 pour un type int.
     * Utilisé notamment par les Settings, où les variables d'environnement sont
     * toujours des chaînes.
     */
    public function lax(): self
    {
        $copy = $this->copy([
            'coerce' => true,
            'item' => $this->item?->lax(),
            'key' => $this->key?->lax(),
            'value' => $this->value?->lax(),
            'types' => array_map(static fn (self $type) => $type->lax(), $this->types),
        ]);

        return $copy;
    }

    /**
     * Copie le type en remplaçant certains attributs.
     *
     * @param array<string, mixed> $overrides
     */
    protected function copy(array $overrides = []): self
    {
        $properties = [
            'kind' => $this->kind,
            'class' => $this->class,
            'item' => $this->item,
            'key' => $this->key,
            'value' => $this->value,
            'types' => $this->types,
            'literals' => $this->literals,
            'var' => $this->var,
            'constraints' => $this->constraints,
            'metadata' => $this->metadata,
            'coerce' => $this->coerce,
        ];

        return new Type(...array_merge($properties, $overrides));
    }

    public function isNullable(): bool
    {
        if ($this->kind === self::NULL || $this->kind === self::ANY) {
            return true;
        }

        if ($this->kind === self::UNION) {
            foreach ($this->types as $type) {
                if ($type->isNullable()) {
                    return true;
                }
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Validation / sérialisation / schéma
    // ------------------------------------------------------------------

    /**
     * Valide et convertit une valeur brute.
     *
     * @param array<string, Type|string> $bindings Liaisons des TypeVar.
     */
    public function validate(mixed $value, array $bindings = [], ?string $label = null): mixed
    {
        $type = $this->resolve($bindings);

        if ($value !== null) {
            foreach ($type->constraints as $constraint) {
                $constraint->check($value, $label ?? 'La valeur');
            }
        }

        return $type->validateValue($value, $bindings, $label);
    }

    /**
     * Convertit une valeur validée en valeur sérialisable.
     */
    public function dump(mixed $value, array $bindings = []): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = $this->resolve($bindings);

        switch ($type->kind) {
            case self::MODEL:
                return $value instanceof BaseModel ? $value->model_dump() : $value;

            case self::VALIDATOR:
                return $value instanceof TypeValidator ? $value->dump() : $value;

            case self::ENUM:
                return $value instanceof BackedEnum ? $value->value : $value;

            case self::OBJECT:
                return $value instanceof DateTimeInterface ? $value->format(DATE_ATOM) : $value;

            case self::ARRAY:
                return $type->dumpRecursive($value);

            case self::LIST:
                return array_values(array_map(
                    static fn (mixed $item) => $type->item?->dump($item, $bindings),
                    $value
                ));

            case self::MAP:
                $result = [];
                foreach ($value as $key => $item) {
                    $result[$key] = $type->value?->dump($item, $bindings);
                }

                return $result;

            case self::UNION:
                foreach ($type->types as $member) {
                    if ($member->matches($value)) {
                        return $member->dump($value, $bindings);
                    }
                }

                return $value;

            default:
                return $value;
        }
    }

    /**
     * Indique si la valeur correspond au type (contrôle léger, sans coercition).
     */
    public function matches(mixed $value): bool
    {
        return match ($this->kind) {
            self::ANY => true,
            self::NULL => $value === null,
            self::BOOL => is_bool($value),
            self::INT => is_int($value),
            self::FLOAT => is_float($value) || is_int($value),
            self::STR => is_string($value),
            self::ARRAY, self::LIST, self::MAP => is_array($value),
            self::MODEL, self::VALIDATOR, self::OBJECT, self::ENUM => $value instanceof $this->class,
            self::LITERAL => in_array($value, $this->literals, true),
            self::UNION => $this->anyMatches($value),
            default => false,
        };
    }

    /**
     * Sérialise récursivement un tableau (modèles imbriqués, types validés).
     *
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    protected function dumpRecursive(array $values): array
    {
        foreach ($values as $key => $item) {
            if ($item instanceof BaseModel) {
                $values[$key] = $item->model_dump();
            } elseif ($item instanceof TypeValidator) {
                $values[$key] = $item->dump();
            } elseif (is_array($item)) {
                $values[$key] = $this->dumpRecursive($item);
            }
        }

        return $values;
    }

    private function anyMatches(mixed $value): bool
    {
        foreach ($this->types as $type) {
            if ($type->matches($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Schéma JSON du type (sous-ensemble de la spécification OpenAPI/JSON Schema).
     *
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        $schema = $this->baseSchema();

        foreach ($this->constraints as $constraint) {
            $schema += $constraint->jsonSchema();
        }

        return $schema;
    }

    public function describe(): string
    {
        return match ($this->kind) {
            self::ANY => 'mixed',
            self::NULL => 'null',
            self::BOOL => 'bool',
            self::INT => 'int',
            self::FLOAT => 'float',
            self::STR => 'string',
            self::ARRAY => 'array',
            self::LIST => 'list[' . ($this->item?->describe() ?? 'mixed') . ']',
            self::MAP => 'map[' . ($this->key?->describe() ?? 'mixed') . ', ' . ($this->value?->describe() ?? 'mixed') . ']',
            self::LITERAL => 'literal(' . implode(', ', array_map(
                static fn (mixed $literal) => var_export($literal, true),
                $this->literals
            )) . ')',
            self::UNION => implode(' | ', array_map(static fn (self $type) => $type->describe(), $this->types)),
            self::TYPEVAR => '$' . ($this->var?->name ?? 'T'),
            default => $this->class !== null
                ? substr((string) strrchr('\\' . $this->class, '\\'), 1)
                : $this->kind,
        };
    }

    // ------------------------------------------------------------------
    // Interne
    // ------------------------------------------------------------------

    /**
     * Remplace un TypeVar par sa liaison.
     *
     * @param array<string, Type|string> $bindings
     */
    protected function resolve(array $bindings): self
    {
        if ($this->kind !== self::TYPEVAR || $this->var === null) {
            return $this;
        }

        $bound = $bindings[$this->var->name] ?? $this->var->bound ?? $this->var->default;

        if ($bound === null) {
            throw new ValidationError(sprintf(
                'Variable de type non liée : %s. Fournissez une liaison (bindings).',
                $this->var->name
            ));
        }

        $resolved = self::from($bound);

        // Un conteneur laxiste rend ses membres (y compris liés) laxistes.
        return $this->coerce ? $resolved->lax() : $resolved;
    }

    /**
     * @param array<string, Type|string> $bindings
     */
    protected function validateValue(mixed $value, array $bindings, ?string $label): mixed
    {
        switch ($this->kind) {
            case self::ANY:
                return $value;

            case self::NULL:
                if ($value !== null) {
                    $this->fail($value, $label);
                }

                return null;

            case self::BOOL:
                if (is_bool($value)) {
                    return $value;
                }

                if ($this->coerce) {
                    $normalized = is_string($value) ? strtolower(trim($value)) : $value;

                    if (in_array($normalized, [1, '1', 'true', 'yes', 'on'], true)) {
                        return true;
                    }

                    if (in_array($normalized, [0, '0', 'false', 'no', 'off'], true)) {
                        return false;
                    }
                }

                $this->fail($value, $label);

            case self::INT:
                if (is_int($value)) {
                    return $value;
                }

                if ($this->coerce) {
                    if (is_float($value) && floor($value) === $value) {
                        return (int) $value;
                    }

                    if (is_string($value) && preg_match('/^[+-]?\d+$/', trim($value)) === 1) {
                        return (int) trim($value);
                    }
                }

                $this->fail($value, $label);

            case self::FLOAT:
                if (is_float($value) || is_int($value)) {
                    return $value;
                }

                if ($this->coerce && is_string($value) && is_numeric(trim($value))) {
                    return (float) trim($value);
                }

                $this->fail($value, $label);

            case self::STR:
                if (is_string($value)) {
                    return $value;
                }

                if ($this->coerce && is_scalar($value)) {
                    return (string) $value;
                }

                $this->fail($value, $label);

            case self::ARRAY:
                if (is_array($value)) {
                    return $value;
                }

                $this->fail($value, $label);

            case self::LIST:
                if (!is_array($value) || !array_is_list($value)) {
                    $this->fail($value, $label);
                }

                $result = [];
                foreach ($value as $index => $item) {
                    try {
                        $result[] = $this->item?->validate($item, $bindings);
                    } catch (ValidationError $error) {
                        throw new ValidationError(sprintf(
                            'Élément %d de %s : %s',
                            $index,
                            $label ?? 'la liste',
                            $error->getMessage()
                        ), 0, $error);
                    }
                }

                return $result;

            case self::MAP:
                if (!is_array($value)) {
                    $this->fail($value, $label);
                }

                $result = [];
                foreach ($value as $key => $item) {
                    $validatedKey = $this->validateKey($key, $bindings);

                    try {
                        $result[$validatedKey] = $this->value?->validate($item, $bindings);
                    } catch (ValidationError $error) {
                        throw new ValidationError(sprintf(
                            'Valeur de la clé %s de %s : %s',
                            var_export($validatedKey, true),
                            $label ?? 'la map',
                            $error->getMessage()
                        ), 0, $error);
                    }
                }

                return $result;

            case self::MODEL:
                if ($value instanceof $this->class) {
                    return $value;
                }

                if (is_array($value)) {
                    $class = $this->class;

                    return new $class($value);
                }

                $this->fail($value, $label);

            case self::VALIDATOR:
                if ($value instanceof $this->class) {
                    return $value;
                }

                $class = $this->class;

                return $class::validate($value);

            case self::ENUM:
                if ($value instanceof $this->class) {
                    return $value;
                }

                if (is_subclass_of($this->class, BackedEnum::class) && (is_string($value) || is_int($value))) {
                    $case = $this->class::tryFrom($value);

                    if ($case !== null) {
                        return $case;
                    }
                }

                $this->fail($value, $label);

            case self::OBJECT:
                if ($value instanceof $this->class) {
                    return $value;
                }

                return $this->coerceObject($value, $label);

            case self::LITERAL:
                if (in_array($value, $this->literals, true)) {
                    return $value;
                }

                $this->fail($value, $label);

            case self::UNION:
                $errors = [];

                foreach ($this->types as $member) {
                    try {
                        return $member->validate($value, $bindings);
                    } catch (ValidationError $error) {
                        $errors[] = $error->getMessage();
                    }
                }

                throw new ValidationError(sprintf(
                    '%s : aucune branche de %s ne correspond à %s (%s).',
                    $label ?? 'Valeur',
                    $this->describe(),
                    get_debug_type($value),
                    implode(' | ', $errors)
                ));

            default:
                return $value;
        }
    }

    private function validateKey(mixed $key, array $bindings): string|int
    {
        $type = $this->key;

        if ($type === null) {
            return $key;
        }

        // PHP convertit les clés numériques en int : on rétablit le type demandé.
        if ($type->kind === self::STR && is_int($key)) {
            $key = (string) $key;
        } elseif ($type->kind === self::INT && is_string($key) && preg_match('/^[+-]?\d+$/', $key) === 1) {
            $key = (int) $key;
        }

        $validated = $type->validate($key, $bindings);

        if (!is_string($validated) && !is_int($validated)) {
            throw new ValidationError(sprintf(
                'Clé de map invalide : %s attendu comme clé.',
                $type->describe()
            ));
        }

        return $validated;
    }

    private function coerceObject(mixed $value, ?string $label): mixed
    {
        $class = (string) $this->class;

        if (is_subclass_of($class, DateTimeInterface::class)) {
            if ($value instanceof DateTimeInterface) {
                return $class === DateTimeImmutable::class
                    ? DateTimeImmutable::createFromInterface($value)
                    : new $class($value->format('Y-m-d H:i:s.u'), $value->getTimezone());
            }

            if (is_string($value)) {
                try {
                    return new $class(trim($value));
                } catch (\Throwable) {
                    $this->fail($value, $label);
                }
            }
        }

        $this->fail($value, $label);
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseSchema(): array
    {
        return match ($this->kind) {
            self::ANY => [],
            self::NULL => ['type' => 'null'],
            self::BOOL => ['type' => 'boolean'],
            self::INT => ['type' => 'integer'],
            self::FLOAT => ['type' => 'number'],
            self::STR => ['type' => 'string'],
            self::ARRAY => ['type' => 'array'],
            self::LIST => ['type' => 'array', 'items' => $this->item?->schema() ?? []],
            self::MAP => ['type' => 'object', 'additionalProperties' => $this->value?->schema() ?? []],
            self::LITERAL => ['enum' => $this->literals],
            self::UNION => ['anyOf' => array_map(static fn (self $type) => $type->schema(), $this->types)],
            self::MODEL => ['type' => 'object', 'title' => $this->class],
            self::ENUM => ['type' => 'string', 'title' => $this->class],
            self::VALIDATOR => ['type' => 'string', 'format' => self::formatFor($this->class)],
            self::OBJECT => ['type' => 'object', 'title' => $this->class],
            default => [],
        };
    }

    private static function formatFor(?string $class): string
    {
        return match ($class) {
            \Svinx\Validator\Validators\EmailStr::class => 'email',
            \Svinx\Validator\Validators\HttpUrl::class => 'uri',
            \Svinx\Validator\Validators\IPv4Address::class => 'ipv4',
            \Svinx\Validator\Validators\IPv6Address::class => 'ipv6',
            \Svinx\Validator\Validators\UUID::class => 'uuid',
            \Svinx\Validator\Validators\Date::class => 'date',
            \Svinx\Validator\Validators\Datetime::class => 'date-time',
            \Svinx\Validator\Validators\Decimal::class => 'decimal',
            default => 'unknown',
        };
    }

    protected function fail(mixed $value, ?string $label): never
    {
        throw new ValidationError(sprintf(
            '%s : %s attendu, %s donné%s.',
            $label ?? 'Valeur',
            $this->describe(),
            get_debug_type($value),
            is_scalar($value) ? ' (' . var_export($value, true) . ')' : ''
        ));
    }
}
