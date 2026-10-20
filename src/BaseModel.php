<?php

namespace Svinx\Validator;

use ReflectionClass;
use ReflectionProperty;
use Svinx\Validator\Typing\Type;

abstract class BaseModel
{
    /** @var array<string, array<string, array{field: ?Field, type: Type, alias: ?string}>> */
    private static array $_fieldsMeta = [];

    private static array $_fieldValidatorsMeta = [];
    private static array $_configs = [];

    /** @var array<string, mixed> Champs inconnus conservés quand extra = allow */
    private array $_extraFields = [];

    public function __construct(array $data = [])
    {
        $this->validateAndAssign($data);
    }

    /**
     * Configuration du modèle (équivalent de `model_config` chez Pydantic).
     */
    public static function model_config(): Config
    {
        return new Config();
    }

    protected static function config(): Config
    {
        $class = static::class;

        return self::$_configs[$class] ??= static::model_config();
    }

    private function validateAndAssign(array $data): void
    {
        $config = static::config();
        $fields = static::buildFieldsMeta();
        $validators = $this->getFieldValidatorsMeta();

        foreach ($fields as $property => $meta) {
            $value = $this->extractValue($data, $property, $meta);
            $value = $config->normalizeString($value);
            $value = $this->runFieldValidators($value, $property, FieldValidator::BEFORE, $validators);
            $value = $meta['type']->validate($value, [], "Le champ '$property'");
            $value = $this->runFieldValidators($value, $property, FieldValidator::AFTER, $validators);

            try {
                $this->{$property} = $value;
            } catch (\TypeError $e) {
                throw new ValidationError(
                    "Le validateur du champ '$property' a retourné un type incompatible "
                    . "(attendu : " . (new ReflectionProperty($this, $property))->getType() . ", "
                    . gettype($value) . " donné).",
                    0,
                    $e
                );
            }
        }

        $this->collectExtraFields($data, $fields, $config);
    }

    /**
     * Extrait la valeur brute d'un champ, en respectant alias, nom, défauts et nullabilité.
     *
     * @param array<string, mixed> $data
     * @param array{field: ?Field, type: Type, alias: ?string} $meta
     */
    private function extractValue(array $data, string $property, array $meta): mixed
    {
        $alias = $meta['alias'];
        $populateByName = static::config()->populate_by_name;

        $keys = [];

        if ($alias !== null) {
            $keys[] = $alias;
        }

        if ($populateByName || $alias === null) {
            $keys[] = $property;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        $field = $meta['field'];

        if ($field !== null && $field->default !== null) {
            return $field->default;
        }

        $propertyReflection = new ReflectionProperty($this, $property);

        if ($propertyReflection->hasDefaultValue()) {
            return $this->{$property};
        }

        if ($meta['type']->isNullable()) {
            return null;
        }

        throw new ValidationError("Le champ '$property' est requis.");
    }

    /**
     * Gère les clés de données qui ne correspondent à aucun champ.
     *
     * @param array<string, mixed> $data
     * @param array<string, array{field: ?Field, type: Type, alias: ?string}> $fields
     */
    private function collectExtraFields(array $data, array $fields, Config $config): void
    {
        $known = [];

        foreach ($fields as $property => $meta) {
            $known[$property] = true;

            if ($meta['alias'] !== null) {
                $known[$meta['alias']] = true;
            }
        }

        $unknown = array_diff_key($data, $known);

        if ($unknown === [] || $config->extra === Config::EXTRA_IGNORE) {
            return;
        }

        if ($config->extra === Config::EXTRA_FORBID) {
            throw new ValidationError(
                'Champs inconnus : ' . implode(', ', array_keys($unknown)) . '.'
            );
        }

        $this->_extraFields = $unknown;
    }

    /**
     * Champs libres conservés lorsque `extra: allow` est configuré.
     *
     * @return array<string, mixed>
     */
    public function extra(): array
    {
        return $this->_extraFields;
    }

    /**
     * Exécute les validateurs personnalisés déclarés via #[FieldValidator].
     *
     * @param array<string, list<array{method: string, mode: string, always: bool}>> $validators
     */
    private function runFieldValidators(mixed $value, string $property, string $mode, array $validators): mixed
    {
        $candidates = $validators[$property] ?? [];

        if (isset($validators[FieldValidator::ANY])) {
            $candidates = array_merge($candidates, $validators[FieldValidator::ANY]);
        }

        foreach ($candidates as $validator) {
            if ($validator['mode'] !== $mode) {
                continue;
            }

            // Une valeur absente/nulle n'est validée que si le validateur le demande explicitement.
            if ($value === null && !$validator['always']) {
                continue;
            }

            try {
                $result = $this->{$validator['method']}(static::class, $value);
            } catch (ValidationError $e) {
                throw $e;
            } catch (\TypeError $e) {
                throw new ValidationError(
                    "Le validateur '{$validator['method']}' du champ '$property' a reçu une valeur incompatible : "
                    . $e->getMessage(),
                    0,
                    $e
                );
            } catch (\Throwable $e) {
                throw new ValidationError(
                    "Échec de la validation du champ '$property' ({$validator['method']}) : " . $e->getMessage(),
                    0,
                    $e
                );
            }

            // Retour null : la valeur courante est conservée (permet de valider sans transformer).
            if ($result !== null) {
                $value = $result;
            }
        }

        return $value;
    }

    /**
     * Métadonnées des champs du modèle (type, contraintes, alias).
     *
     * @return array<string, array{field: ?Field, type: Type, alias: ?string}>
     */
    protected static function buildFieldsMeta(): array
    {
        $class = static::class;

        if (isset(self::$_fieldsMeta[$class])) {
            return self::$_fieldsMeta[$class];
        }

        $config = static::config();
        $reflection = new ReflectionClass($class);
        $fields = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic() || !$property->isPublic()) {
                continue;
            }

            $attributes = $property->getAttributes(Field::class);
            $field = $attributes !== [] ? $attributes[0]->newInstance() : null;

            $alias = $config->aliasFor($property->getName(), $field?->alias);
            $type = static::buildType($property, $field);

            $fields[$property->getName()] = [
                'field' => $field,
                'type' => $type,
                'alias' => $alias,
            ];
        }

        return self::$_fieldsMeta[$class] = $fields;
    }

    /**
     * Construit le type d'une propriété (surchargeable, ex. par les Settings
     * qui activent la coercition pour les variables d'environnement).
     */
    protected static function buildType(ReflectionProperty $property, ?Field $field): Type
    {
        $type = Type::fromReflection($property->getType());

        return $field !== null ? $type->withConstraints($field) : $type;
    }

    /**
     * Collecte les validateurs personnalisés du modèle, indexés par propriété.
     *
     * Les clés acceptées par #[FieldValidator] sont le nom de propriété, son alias,
     * ou '*' (tous les champs). Une même méthode peut cibler plusieurs champs.
     *
     * @return array<string, list<array{method: string, mode: string, always: bool}>>
     */
    private function getFieldValidatorsMeta(): array
    {
        $class = static::class;

        if (isset(self::$_fieldValidatorsMeta[$class])) {
            return self::$_fieldValidatorsMeta[$class];
        }

        $fields = static::buildFieldsMeta();

        // Table de résolution : nom de propriété et alias -> nom de propriété.
        $lookup = [];
        foreach ($fields as $property => $meta) {
            $lookup[$property] = $property;
            if ($meta['alias'] !== null) {
                $lookup[$meta['alias']] = $property;
            }
        }

        $validators = [];
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(FieldValidator::class);
            if ($attributes === []) {
                continue;
            }

            if (!$method->isPublic() || $method->isStatic() || $method->isAbstract()) {
                throw new ValidationError(sprintf(
                    "Le validateur '%s::%s' doit être une méthode publique, non statique et non abstraite.",
                    $class,
                    $method->getName()
                ));
            }

            foreach ($attributes as $attribute) {
                /** @var FieldValidator $validator */
                $validator = $attribute->newInstance();
                $keys = $validator->key ?? self::deriveValidatorKey($method->getName());
                $keys = is_array($keys) ? $keys : [$keys];

                foreach ($keys as $key) {
                    $targets = $key === FieldValidator::ANY
                        ? array_keys($fields)
                        : [$lookup[$key] ?? null];

                    if ($targets === [null]) {
                        throw new ValidationError(sprintf(
                            "Le validateur '%s::%s' cible le champ inconnu '%s'.",
                            $class,
                            $method->getName(),
                            $key
                        ));
                    }

                    foreach ($targets as $property) {
                        $validators[$property][] = [
                            'method' => $method->getName(),
                            'mode' => $validator->mode,
                            'always' => $validator->always,
                        ];
                    }
                }
            }
        }

        return self::$_fieldValidatorsMeta[$class] = $validators;
    }

    /**
     * Déduit la cible d'un validateur depuis son nom : `validate_<champ>` => `<champ>`.
     */
    private static function deriveValidatorKey(string $methodName): string
    {
        return str_starts_with($methodName, 'validate_') && strlen($methodName) > 9
            ? substr($methodName, 9)
            : $methodName;
    }

    /**
     * @return array<string, mixed>
     */
    public function model_dump(?array $include = null, ?array $exclude = null): array
    {
        $result = [];

        foreach (static::buildFieldsMeta() as $property => $meta) {
            if ($include !== null && !in_array($property, $include, true)) {
                continue;
            }

            if ($exclude !== null && in_array($property, $exclude, true)) {
                continue;
            }

            $result[$meta['alias'] ?? $property] = $meta['type']->dump($this->{$property});
        }

        foreach ($this->_extraFields as $key => $value) {
            $result[$key] = $value instanceof BaseModel ? $value->model_dump() : $value;
        }

        return $result;
    }

    public function model_dump_json(int $flags = 0): string
    {
        return json_encode($this->model_dump(), $flags);
    }

    public static function model_validate(array $data): static
    {
        return new static($data);
    }

    public static function model_validate_json(string $json): static
    {
        return new static(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Schéma JSON du modèle (sous-ensemble d'OpenAPI / JSON Schema).
     *
     * @return array<string, mixed>
     */
    public static function model_schema(): array
    {
        $properties = [];
        $required = [];

        foreach (static::buildFieldsMeta() as $property => $meta) {
            $properties[$meta['alias'] ?? $property] = $meta['type']->schema();

            $hasDefault = (new ReflectionClass(static::class))
                ->getProperty($property)
                ->hasDefaultValue();

            if (!$hasDefault && $meta['field']?->default === null && !$meta['type']->isNullable()) {
                $required[] = $meta['alias'] ?? $property;
            }
        }

        return [
            'title' => static::config()->title ?? (new ReflectionClass(static::class))->getShortName(),
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }
}
