<?php

namespace Bcl\Toolkit\Models\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\SchemalessAttributes\Casts\SchemalessAttributes;

/**
 * The schemaless escape hatch: a `metadata` JSON column cast via
 * spatie/laravel-schemaless-attributes, plus an `md` staging attribute
 * so forms can write metadata without a per-key mutator.
 *
 * Schema requirements: the model's table needs a nullable `metadata`
 * json column. Without it a plain save still no-ops safely, but staging
 * `md` throws — silently dropping data the caller staged is a data-loss
 * footgun, and letting `md` reach the insert only buys an opaque
 * "no column named md" from the driver.
 *
 * Discipline: metadata is for sparse, per-instance display/config data.
 * The moment a key is queried, sorted, joined, or validated across rows,
 * promote it to a real column.
 */
trait HasMetaData
{
    public function initializeHasMetaData()
    {
        $this->casts['metadata'] = SchemalessAttributes::class;
    }

    public function scopeWithMetadata(): Builder
    {
        return $this->metadata->modelScope();
    }

    protected static function bootHasMetaData(): void
    {
        static::saving(function ($model) {

            if (! $model->hasMetadataColumn()) {
                // Nothing staged: stay cheap and let the save through.
                if (! array_key_exists('md', $model->getAttributes())) {
                    return;
                }

                throw new RuntimeException(sprintf(
                    'Staged `md` metadata on %s but table %s has no `metadata` column '
                    .'— add the column or stop staging md.',
                    $model::class,
                    $model->getTable(),
                ));
            }

            $md = $model->getAttribute('md') ?? [];

            if (is_string($md)) {
                $decoded = json_decode($md, true);
                $md = is_array($decoded) ? $decoded : [];
            }

            foreach ($md as $k => $v) {
                if ($v instanceof CarbonInterface) {
                    $md[$k] = $v->toDateString();
                }
            }

            foreach ($md as $k => $v) {
                if ($k === 'original_filename') {
                    continue;
                }
                $model->metadata->set($k, $v);
            }

            unset($model->md);
        });
    }

    protected function hasMetadataColumn(): bool
    {
        return Schema::hasColumn($this->getTable(), 'metadata');
    }

    public function clearAllMetadata(): void
    {
        foreach (array_keys($this->metadata->toArray()) as $key) {
            $this->metadata->forget($key);
        }
    }
}
