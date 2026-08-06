<?php

namespace Bcl\Toolkit\Brand;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use JsonSerializable;
use ValueError;

/**
 * A public identity from the app's brand registry (config/brands.php).
 * Config-backed rather than an enum so each org registers its own brands;
 * the enum surface (from/tryFrom/->value, identity comparison via flyweight
 * instances) is preserved so call sites read the same.
 */
final class Brand implements Castable, JsonSerializable
{
    /** @var array<string, self> */
    private static array $instances = [];

    private function __construct(public readonly string $value) {}

    public static function from(string $slug): self
    {
        return self::tryFrom($slug) ?? throw new ValueError(
            "\"{$slug}\" is not a registered brand — add it to brands.registry."
        );
    }

    public static function tryFrom(?string $slug): ?self
    {
        if ($slug === null || $slug === '' || config("brands.registry.{$slug}") === null) {
            return null;
        }

        return self::$instances[$slug] ??= new self($slug);
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return array_map(
            fn (string $slug) => self::from($slug),
            array_keys(config('brands.registry', [])),
        );
    }

    /**
     * Enum-compatible alias of all().
     *
     * @return list<self>
     */
    public static function cases(): array
    {
        return self::all();
    }

    /**
     * The brand named by brands.default, if registered.
     */
    public static function default(): ?self
    {
        return self::tryFrom(config('brands.default'));
    }

    /**
     * Resolve a brand from an HTTP Host header, falling back to the
     * configured default.
     */
    public static function fromHost(?string $host): ?self
    {
        foreach (self::all() as $brand) {
            if (strcasecmp((string) $host, $brand->domain()) === 0) {
                return $brand;
            }
        }

        return self::default();
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return config("brands.registry.{$this->value}");
    }

    public function displayName(): string
    {
        return $this->config()['name'];
    }

    public function domain(): string
    {
        return $this->config()['domain'];
    }

    public function fromAddress(): string
    {
        return $this->config()['from_address'];
    }

    public function fromName(): string
    {
        return $this->config()['from_name'];
    }

    /**
     * Named mailer this brand sends through (null = app default). Each brand
     * needs a transport whose sending domain aligns with its From domain,
     * or recipient DMARC checks fail.
     */
    public function mailer(): ?string
    {
        return $this->config()['mailer'] ?? null;
    }

    /**
     * Where publish serves this brand's shared sites (first-level wildcard).
     */
    public function baseDomain(): ?string
    {
        return $this->config()['base_domain'] ?? null;
    }

    /**
     * Email domain marking someone as this brand's team (corporate identity).
     */
    public function teamEmailDomain(): ?string
    {
        return $this->config()['team_email_domain'] ?? null;
    }

    /**
     * Build an absolute URL for a path on this brand's domain.
     */
    public function url(string $path = ''): string
    {
        $scheme = config('brands.scheme', 'https');

        return $scheme.'://'.$this->domain().'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, string>
     */
    public function theme(): array
    {
        return $this->config()['theme'];
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    /**
     * Eloquent cast: store the slug, hydrate the flyweight instance —
     * '"brand" => Brand::class' works exactly like the old enum cast.
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get($model, string $key, $value, array $attributes): ?Brand
            {
                return Brand::tryFrom($value);
            }

            public function set($model, string $key, $value, array $attributes): ?string
            {
                return match (true) {
                    $value === null => null,
                    $value instanceof Brand => $value->value,
                    default => Brand::from((string) $value)->value,
                };
            }
        };
    }
}
