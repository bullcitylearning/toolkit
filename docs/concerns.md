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

**Encrypted-cast attributes are excluded automatically** (since v1.2.0), on top
of that list. Activitylog reads attributes through their accessors, and an
`encrypted` cast decrypts on access — so `logAll()` would write the decrypted
old *and* new secret into `activity_log` in plaintext. FileClerk proved it: a
Dropbox token rotation logged both tokens in the clear. `$hidden` is no defence
either: activitylog reads through `getAttribute()` and never calls `toArray()`.

Both spellings of an encrypted cast are covered (the class casts since v1.2.1),
and an excluded attribute is dropped from the diff entirely — no plaintext, no
ciphertext, no key at all:

```php
protected $casts = [
    'dropbox_token' => 'encrypted',                      // never logged
    'settings' => 'encrypted:array',                     // never logged
    'profile' => AsEncryptedArrayObject::class,          // never logged
    'tokens' => AsEncryptedCollection::of(Token::class), // never logged
];
```

- **String casts:** `encrypted`, or anything starting with `encrypted:`
  (`encrypted:array`, `encrypted:collection`, `encrypted:json`,
  `encrypted:object`).
- **Class casts:** any cast whose class basename starts with `AsEncrypted` —
  matched by prefix rather than by an exact list of the two Laravel ships
  today, so the next one is covered on arrival. Cast arguments are stripped
  before the basename is read (`AsEncryptedCollection::of(Token::class)`
  serialises to `…\AsEncryptedCollection:,App\Token`, whose literal basename
  is `Token`), which is what makes the parameterised forms match.

The rule can only ever *widen* exclusions, so over-matching is safe by
construction — an app cast of its own named `AsEncryptedSomething` is excluded
too, which is the behavior you want.

There is deliberately **no opt-out flag**. A model that genuinely must log an
encrypted attribute overrides `getActivitylogOptions()` outright and owns that
decision in its own file, where a reader can see it — and an override has to
re-apply `encryptedCastAttributes()` by hand, or it re-opens this hole.

Any hand-rolled activity logging elsewhere in an app must honor the same rule.

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

**Without the column** a plain save still passes straight through, so the trait
is safe on a table that hasn't been migrated yet — but staging `md` on such a
model throws a `RuntimeException` naming the model, the table, and the fix
(since v1.2.0):

```
Staged `md` metadata on App\Models\Widget but table widgets has no `metadata`
column — add the column or stop staging md.
```

Failing loudly beats both alternatives: silently discarding metadata the caller
staged is a data-loss footgun, and letting `md` through to the insert only buys
an opaque "no column named md" from the driver. The fix is one of the two the
message names — add the migration, or stop binding `md` on that model.

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
