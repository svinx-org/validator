<?php

namespace Svinx\Validator\Validators;

/**
 * URL HTTP(S) valide (équivalent de `HttpUrl` de Pydantic).
 *
 *     public HttpUrl $website;
 *
 * Seuls les schémas `http` et `https` sont acceptés ; un hôte est obligatoire.
 */
class HttpUrl extends AbstractType
{
    public const SCHEMES = ['http', 'https'];

    protected function parse(mixed $raw): string
    {
        $url = trim(self::requireString($raw, $this->typeName()));

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            self::fail(sprintf('HttpUrl invalide : %s n\'est pas une URL valide.', var_export($url, true)));
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, self::SCHEMES, true)) {
            self::fail(sprintf(
                'HttpUrl invalide : schéma "%s" non autorisé (attendu : %s).',
                $scheme,
                implode(' ou ', self::SCHEMES)
            ));
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            self::fail(sprintf('HttpUrl invalide : %s n\'est pas une URL valide.', var_export($url, true)));
        }

        return $url;
    }

    public function scheme(): string
    {
        return strtolower((string) parse_url($this->value, PHP_URL_SCHEME));
    }

    public function host(): string
    {
        return (string) parse_url($this->value, PHP_URL_HOST);
    }

    public function port(): ?int
    {
        $port = parse_url($this->value, PHP_URL_PORT);

        return is_int($port) ? $port : null;
    }

    public function path(): string
    {
        return (string) parse_url($this->value, PHP_URL_PATH);
    }

    public function query(): ?string
    {
        $query = parse_url($this->value, PHP_URL_QUERY);

        return is_string($query) ? $query : null;
    }

    public function fragment(): ?string
    {
        $fragment = parse_url($this->value, PHP_URL_FRAGMENT);

        return is_string($fragment) ? $fragment : null;
    }
}
