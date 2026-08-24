<?php

namespace Bcl\Toolkit\Models\Concerns;

use Illuminate\Support\Facades\App;
use Spatie\Translatable\HasTranslations as BaseHasTranslations;

/**
 * App-owned wrapper around spatie/laravel-translatable.
 *
 * Fixes the vendor trait's toArray(): instead of emitting the raw
 * per-locale JSON map for every translatable field, emit the string for
 * the active locale — which is what APIs, MCP resources and Blade all
 * actually want.
 *
 * Schema requirements: each translatable column is a json column, and
 * the model declares `public array $translatable = [...]` as usual.
 */
trait HasTranslations
{
    use BaseHasTranslations;

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        $attributes = parent::toArray();
        foreach ($this->getTranslatableAttributes() as $field) {
            $attributes[$field] = $this->getTranslation($field, $this->getLocale());
        }

        return $attributes;
    }

    /**
     * The locale to render translations in: an explicit per-instance
     * override first, then the visitor's session choice, then the app
     * locale.
     */
    public function getLocale(): string
    {
        if ($this->translationLocale) {
            return $this->translationLocale;
        }

        return $this->sessionLocale() ?: App::getLocale();
    }

    /**
     * session('locale'), but only where a session actually exists —
     * queue workers, console commands and MCP requests have none, and
     * resolving one there either errors or silently starts a store.
     */
    protected function sessionLocale(): ?string
    {
        $request = request();

        if (! $request->hasSession()) {
            return null;
        }

        return $request->session()->get('locale');
    }
}
