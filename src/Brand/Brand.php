<?php

namespace Bcl\Toolkit\Brand;

enum Brand: string
{
    case Bcl = 'bcl';
    case Bcb = 'bcb';

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return config("brands.{$this->value}");
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

    /**
     * Resolve a brand from an HTTP Host header, defaulting to BCL.
     */
    public static function fromHost(?string $host): self
    {
        foreach (self::cases() as $brand) {
            if (strcasecmp((string) $host, $brand->domain()) === 0) {
                return $brand;
            }
        }

        return self::Bcl;
    }
}
