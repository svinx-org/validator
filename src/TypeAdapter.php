<?php

namespace Svinx\Validator;

use Svinx\Validator\Typing\Type;
use Svinx\Validator\Typing\TypeVar;

/**
 * Adaptateur de type autonome, équivalent de `TypeAdapter` de Pydantic.
 *
 * Permet de valider et sérialiser une valeur sans passer par un modèle :
 *
 *     $adapter = TypeAdapter::of(Type::list(EmailStr::class));
 *     $adapter->validate(['a@b.co', 'c@d.co']);      // EmailStr[]
 *     $adapter->dump($emails);                        // ['a@b.co', 'c@d.co']
 *     $adapter->json_schema();                        // ["type" => "array", "items" => ["format" => "email"]]
 *
 * Les TypeVar sont liées à la construction :
 *
 *     TypeAdapter::of(Type::list(TypeVar::of('T')), ['T' => Type::int()])->validate([1, 2]);
 */
final class TypeAdapter
{
    /**
     * @param array<string, Type|string> $bindings
     */
    private function __construct(
        public readonly Type $type,
        public readonly array $bindings = [],
    ) {}

    /**
     * @param array<string, Type|string> $bindings
     */
    public static function of(Type|string|TypeVar $type, array $bindings = []): self
    {
        return new self(Type::from($type), $bindings);
    }

    /**
     * Adaptateur d'un modèle complet.
     */
    public static function model(string $class): self
    {
        if (!is_subclass_of($class, BaseModel::class)) {
            throw new ValidationError(sprintf('%s n\'est pas un modèle de validation.', $class));
        }

        return new self(Type::model($class));
    }

    /**
     * Adaptateur en mode laxiste (coercition des scalaires).
     */
    public function lax(): self
    {
        return new self($this->type->lax(), $this->bindings);
    }

    public function validate(mixed $value): mixed
    {
        return $this->type->validate($value, $this->bindings);
    }

    public function validate_json(string $json): mixed
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $this->validate($decoded);
    }

    public function dump(mixed $value): mixed
    {
        return $this->type->dump($value, $this->bindings);
    }

    public function dump_json(mixed $value, int $flags = 0): string
    {
        return json_encode($this->dump($value), $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * Schéma JSON du type.
     *
     * @return array<string, mixed>
     */
    public function json_schema(): array
    {
        return $this->type->schema();
    }

    public function json_schema_json(int $flags = JSON_PRETTY_PRINT): string
    {
        return (string) json_encode($this->json_schema(), $flags | JSON_THROW_ON_ERROR);
    }
}
