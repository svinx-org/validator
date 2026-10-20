<?php

namespace Svinx\Validator\Settings;

use ReflectionProperty;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;
use Svinx\Validator\Typing\Type;

/**
 * Modèle de configuration, équivalent de `BaseSettings` de Pydantic.
 *
 *     class AppSettings extends BaseSettings
 *     {
 *         public static function env_prefix(): string { return 'APP_'; }
 *
 *         public string $env;
 *         public bool $debug = false;
 *         public int $port = 8080;
 *         public EmailStr $admin_email;
 *         public SecretStr $api_key;
 *     }
 *
 * Les valeurs sont lues dans cet ordre de priorité :
 *   1. arguments passés au constructeur (`new AppSettings(['port' => 9000])`) ;
 *   2. variables d'environnement (`APP_PORT`, `APP_ADMIN_EMAIL`...) ;
 *   3. fichier .env éventuel (env_file()) ;
 *   4. valeurs par défaut des propriétés.
 *
 * Les variables d'environnement étant toujours des chaînes, les types du
 * modèle sont validés en mode laxiste ("8080" devient 8080 pour un int).
 */
abstract class BaseSettings extends BaseModel
{
    /**
     * @param array<string, mixed>|null $data Valeurs explicites (prioritaires).
     * @param array<string, string>|null $env Environnement simulé (tests).
     */
    public function __construct(?array $data = null, ?array $env = null)
    {
        $environment = $env ?? static::readEnvironment(static::env_file());
        $values = static::valuesFromEnvironment($environment);

        parent::__construct(array_replace_recursive($values, $data ?? []));
    }

    /**
     * Préfixe des variables d'environnement (ex. 'APP_').
     */
    public static function env_prefix(): string
    {
        return '';
    }

    /**
     * Chemin d'un fichier .env facultatif (les variables réelles sont prioritaires).
     */
    public static function env_file(): ?string
    {
        return null;
    }

    /**
     * Séparateur des Settings imbriqués (ex. '__' : APP_DB__HOST).
     */
    public static function env_nested_delimiter(): ?string
    {
        return null;
    }

    /**
     * Respecte la casse des noms de variables d'environnement.
     */
    public static function case_sensitive(): bool
    {
        return false;
    }

    /**
     * Charge les Settings depuis l'environnement.
     *
     * @param array<string, string>|null $env
     */
    public static function load(?array $env = null): static
    {
        return new static(null, $env);
    }

    /**
     * Les Settings tolèrent les chaînes d'environnement pour les scalaires.
     */
    protected static function buildType(ReflectionProperty $property, ?Field $field): Type
    {
        return parent::buildType($property, $field)->lax();
    }

    /**
     * Lit l'environnement courant (fichier .env puis variables réelles).
     *
     * @return array<string, string>
     */
    public static function readEnvironment(?string $file = null): array
    {
        $values = $file !== null ? static::parseEnvFile($file) : [];

        foreach ($_ENV as $key => $value) {
            if (is_string($value)) {
                $values[(string) $key] = $value;
            }
        }

        $all = getenv();

        if (is_array($all)) {
            foreach ($all as $key => $value) {
                if (is_string($value)) {
                    $values[(string) $key] = $value;
                }
            }
        }

        return $values;
    }

    /**
     * Analyse minimaliste d'un fichier .env (KEY=VALUE, commentaires, quotes).
     *
     * @return array<string, string>
     */
    public static function parseEnvFile(string $file): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }

        $values = [];
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];

                if (($first === '"' || $first === "'") && $first === $last) {
                    $value = substr($value, 1, -1);

                    if ($first === '"') {
                        $value = stripcslashes($value);
                    }
                }
            }

            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * Convertit les variables d'environnement en tableau de données du modèle.
     *
     * @param array<string, string> $environment
     * @return array<string, mixed>
     */
    protected static function valuesFromEnvironment(array $environment): array
    {
        $caseSensitive = static::case_sensitive();
        $lookup = [];

        foreach ($environment as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            $lookup[$caseSensitive ? (string) $key : strtolower((string) $key)] = $value;
        }

        $delimiter = static::env_nested_delimiter();
        $values = [];

        foreach (static::buildFieldsMeta() as $property => $meta) {
            $candidates = array_values(array_unique(array_filter([
                static::envKey($property),
                $meta['alias'] !== null ? static::envKey($meta['alias']) : null,
            ])));

            foreach ($candidates as $candidate) {
                $needle = $caseSensitive ? $candidate : strtolower($candidate);

                if (array_key_exists($needle, $lookup)) {
                    $values[$property] = $lookup[$needle];
                    break;
                }
            }

            if (isset($values[$property]) || $delimiter === null || !self::isModelType($meta['type'])) {
                continue;
            }

            $nested = static::nestedFromEnvironment($lookup, $candidates, $delimiter, $caseSensitive);

            if ($nested !== []) {
                $values[$property] = $nested;
            }
        }

        return $values;
    }

    /**
     * Nom de variable d'environnement d'un champ.
     */
    protected static function envKey(string $name): string
    {
        $name = static::case_sensitive() ? $name : self::snakeCase($name);

        return static::env_prefix() . $name;
    }

    protected static function snakeCase(string $name): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }

    /**
     * @param array<string, string> $lookup
     * @param list<string> $candidates
     * @return array<string, mixed>
     */
    protected static function nestedFromEnvironment(array $lookup, array $candidates, string $delimiter, bool $caseSensitive): array
    {
        $separator = $caseSensitive ? $delimiter : strtolower($delimiter);

        foreach ($candidates as $candidate) {
            $prefix = ($caseSensitive ? $candidate : strtolower($candidate)) . $separator;
            $nested = [];

            foreach ($lookup as $key => $value) {
                if (!str_starts_with($key, $prefix) || strlen($key) === strlen($prefix)) {
                    continue;
                }

                $path = preg_split('/' . preg_quote($separator, '/') . '/', substr($key, strlen($prefix))) ?: [];
                $path = array_map(static fn (string $segment) => strtolower($segment), $path);

                self::setNested($nested, $path, $value);
            }

            if ($nested !== []) {
                return $nested;
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $target
     * @param list<string> $path
     */
    private static function setNested(array &$target, array $path, mixed $value): void
    {
        if ($path === []) {
            return;
        }

        $reference = &$target;
        $last = count($path) - 1;

        foreach ($path as $index => $segment) {
            if ($index === $last) {
                $reference[$segment] = $value;

                return;
            }

            if (!isset($reference[$segment]) || !is_array($reference[$segment])) {
                $reference[$segment] = [];
            }

            $reference = &$reference[$segment];
        }
    }

    private static function isModelType(Type $type): bool
    {
        if ($type->kind === Type::MODEL) {
            return true;
        }

        if ($type->kind === Type::UNION) {
            foreach ($type->types as $member) {
                if ($member->kind === Type::MODEL) {
                    return true;
                }
            }
        }

        return false;
    }
}
