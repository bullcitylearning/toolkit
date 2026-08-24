# Model concerns

`Bcl\Toolkit\Models\Concerns\*` — the generic model capability traits, promoted
out of academy_builder (see `jims_dev_way/playbooks/model-composition.md`). Each
is an app-owned seam around a vendor trait: models `use` the toolkit trait, never
the spatie one, so vendor behavior stays tunable in one file.

The three spatie packages are hard requires of the toolkit, so a consuming app
gets them (and their auto-discovered providers) for free.

---

## `LogsModelActivity`

Wraps `spatie/laravel-activitylog` with the defaults every model should have.

```php
use Bcl\Toolkit\Models\Concerns\LogsModelActivity;

class Course extends Model
{
    use LogsModelActivity;
}
```

**Needs:** the package's `activity_log` table. It is publish-only, so run this
once per app:

```bash
php artisan vendor:publish --tag=activitylog-migrations
php artisan migrate
```

**Does:** `logAll()` → `logExcept($this->activityLogExcluded)` →
`logOnlyDirty()` → `dontLogEmptyChanges()` → `useLogName($this->getTable())`.
So: every column except the excluded ones, only what actually changed, no rows
for empty diffs, and the log name is the model's table.

`$activityLogExcluded` defaults to `password`, `remember_token`,
`two_factor_secret`, `two_factor_recovery_codes`, `metadata`, `privacy` —
secrets plus heavy JSON. **Leave `metadata` excluded**: its old+new diff once
produced ~140KB activity_log rows.

**Overriding the exclusions.** PHP forbids redeclaring a trait property with a
different default, so a model that uses the trait cannot write
`protected array $activityLogExcluded = [...]`. Assign it instead:

```php
public function __construct(array $attributes = [])
{
    parent::__construct($attributes);

    $this->activityLogExcluded = [...$this->activityLogExcluded, 'internal_notes'];
}
```

or override `getActivitylogOptions()` outright for anything more involved.
(A model *extending* another model that uses the trait can redeclare the
property normally — that's inheritance, not trait composition.)

**Note for ports from academy_builder:** the toolkit is on activitylog v5, where
diffs live in the new `attribute_changes` column rather than `properties`, and
`dontSubmitEmptyLogs()` is now `dontLogEmptyChanges()` (same semantics).

---

## `HasMetaData`

The schemaless escape hatch: sparse per-instance config/display data without a
migration, via `spatie/laravel-schemaless-attributes`.

```php
use Bcl\Toolkit\Models\Concerns\HasMetaData;

class Course extends Model
{
    use HasMetaData;
}
```

**Needs:** a nullable `metadata` json column on the model's table.

```php
$table->json('metadata')->nullable();
```

**Does:**

- casts `metadata` to `SchemalessAttributes`, so `$model->metadata->set('k', 'v')`
  and `$model->metadata['k']` work;
- `Model::withMetadata('colour', 'red')` scopes a query into the JSON;
- folds a staged `md` attribute into `metadata` on save — that's what forms bind
  to (`md.colour`), no per-key mutators. `md` may be an array or a JSON string;
  `CarbonInterface` values are normalized to date strings; `original_filename` is
  dropped rather than stored;
- `clearAllMetadata()` forgets every key.

`md` is a normal attribute, so `$model->md = [...]` always works; passing it to
`create()`/`fill()` additionally requires `md` in the model's fillable list.

**Without the column** the saving hook returns early instead of erroring, so the
trait is safe on a table that hasn't been migrated yet. It does *not* strip a
staged `md` in that case — don't feed `md` to a model whose table has no
`metadata` column.

**Discipline:** metadata is for sparse, per-instance display/config data. The
moment a key is queried, sorted, joined, or validated across rows, promote it to
a real column in a migration.

---

## `HasTranslations`

Wraps `spatie/laravel-translatable` and fixes its `toArray()`.

```php
use Bcl\Toolkit\Models\Concerns\HasTranslations;

class Course extends Model
{
    use HasTranslations;

    public array $translatable = ['title', 'description'];
}
```

**Needs:** a json column per translatable field.

**Does:** `toArray()` emits the *string* for the active locale for each
translatable field, instead of the raw `{"en": …, "es": …}` map — which is what
APIs, MCP resources and Blade actually want. The vendor accessors
(`getTranslation()`, `getTranslations()`, `setLocale()`) are untouched.

Locale resolution, in order:

1. `$this->translationLocale` — set per instance with `setLocale('es')`;
2. `session('locale')`, but only when the request actually has a session;
3. `App::getLocale()`.

Step 2 is guarded (this is the one deliberate change from academy_builder's
version): queue workers, console commands and MCP requests have no session, and
reaching for one there is at best pointless.
