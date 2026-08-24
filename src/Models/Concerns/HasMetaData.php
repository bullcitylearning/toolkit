<?php

namespace Bcl\Toolkit\Models\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Spatie\SchemalessAttributes\Casts\SchemalessAttributes;

/**
 * The schemaless escape hatch: a `metadata` JSON column cast via
 * spatie/laravel-schemaless-attributes, plus an `md` staging attribute
 * so forms can write metadata without a per-key mutator.
 *
 * Schema requirements: the model's table needs a nullable `metadata`
 * json column. Without it the saving hook no-ops (degrade explicitly)
 * so the trait is safe on tables that haven't been migrated yet.
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
                return;
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
