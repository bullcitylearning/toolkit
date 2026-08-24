<?php

use Bcl\Toolkit\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

class LoggedWidget extends Model
{
    use LogsModelActivity;

    protected $table = 'widgets';

    protected $guarded = [];

    protected $casts = ['metadata' => 'array'];
}

/**
 * Timestamps off so an update touching only excluded columns produces a
 * genuinely empty diff — that's the dontLogEmptyChanges() path.
 */
class StatelessWidget extends Model
{
    use LogsModelActivity;

    protected $table = 'widgets';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['metadata' => 'array'];
}

/**
 * PHP forbids redeclaring a trait property with a different default, so
 * models retune the exclusions by assignment (or by overriding
 * getActivitylogOptions()).
 */
class QuietWidget extends Model
{
    use LogsModelActivity;

    protected $table = 'widgets';

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->activityLogExcluded = ['name'];
    }
}

/**
 * Encrypted casts decrypt on access, and activitylog reads attributes
 * through their accessors — so these are the ones that would land in
 * activity_log in plaintext without the automatic exclusion.
 */
class SecretWidget extends Model
{
    use LogsModelActivity;

    protected $table = 'widgets';

    protected $guarded = [];

    protected $casts = [
        'token' => 'encrypted',
        'payload' => 'encrypted:array',
    ];
}

/**
 * Both kinds of exclusion at once: the model's own list and the
 * automatic encrypted-cast one have to survive the merge.
 */
class SecretQuietWidget extends Model
{
    use LogsModelActivity;

    protected $table = 'widgets';

    protected $guarded = [];

    protected $casts = ['token' => 'encrypted'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->activityLogExcluded = ['name'];
    }
}

beforeEach(function () {
    // The package publishes this migration rather than running it, so
    // the suite executes the vendor stub directly — no schema to drift.
    (require __DIR__.'/../../vendor/spatie/laravel-activitylog/database/migrations/create_activity_log_table.php.stub')->up();

    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('password')->nullable();
        $table->text('token')->nullable();
        $table->text('payload')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
    });
});

it('logs a dirty update under the table name', function () {
    $widget = LoggedWidget::create(['name' => 'first']);

    Activity::query()->delete();

    $widget->update(['name' => 'second']);

    $activity = Activity::query()->sole();

    expect($activity->log_name)->toBe('widgets')
        ->and($activity->description)->toBe('updated')
        ->and($activity->attribute_changes['attributes']['name'])->toBe('second')
        ->and($activity->attribute_changes['old']['name'])->toBe('first');
});

it('logs only the columns that changed', function () {
    $widget = LoggedWidget::create(['name' => 'first', 'password' => 'secret']);

    Activity::query()->delete();

    $widget->update(['name' => 'second']);

    expect(array_keys(Activity::query()->sole()->attribute_changes['attributes']))
        ->toContain('name')
        ->not->toContain('password');
});

it('never logs secrets or metadata', function () {
    LoggedWidget::create([
        'name' => 'first',
        'password' => 'secret',
        'metadata' => ['heavy' => str_repeat('x', 100)],
    ]);

    $attributes = Activity::query()->sole()->attribute_changes['attributes'];

    expect($attributes)->toHaveKey('name')
        ->and($attributes)->not->toHaveKey('password')
        ->and($attributes)->not->toHaveKey('metadata');
});

it('writes nothing when only excluded columns changed', function () {
    $widget = StatelessWidget::create(['name' => 'first']);

    Activity::query()->delete();

    $widget->update(['metadata' => ['note' => 'excluded column moved']]);

    expect(Activity::query()->count())->toBe(0);
});

it('lets a model retune the exclusion list', function () {
    QuietWidget::create(['name' => 'first', 'password' => 'secret']);

    $attributes = Activity::query()->sole()->attribute_changes['attributes'];

    expect($attributes)->not->toHaveKey('name')
        ->and($attributes)->toHaveKey('password');
});

it('never logs an encrypted-cast attribute, in either direction', function () {
    $widget = SecretWidget::create([
        'name' => 'first',
        'token' => 'dropbox-token-one',
        'payload' => ['refresh' => 'one'],
    ]);

    Activity::query()->delete();

    $widget->update([
        'name' => 'second',
        'token' => 'dropbox-token-two',
        'payload' => ['refresh' => 'two'],
    ]);

    $changes = Activity::query()->sole()->attribute_changes;

    // No key at all — not the plaintext, not the ciphertext.
    expect($changes['attributes'])->toHaveKey('name')
        ->and($changes['attributes'])->not->toHaveKey('token')
        ->and($changes['attributes'])->not->toHaveKey('payload')
        ->and($changes['old'])->not->toHaveKey('token')
        ->and($changes['old'])->not->toHaveKey('payload')
        ->and(json_encode($changes))->not->toContain('dropbox-token');
});

it('merges the encrypted exclusions with the model own list', function () {
    SecretQuietWidget::create([
        'name' => 'first',
        'password' => 'secret',
        'token' => 'dropbox-token-one',
    ]);

    $attributes = Activity::query()->sole()->attribute_changes['attributes'];

    expect($attributes)->not->toHaveKey('token')
        ->and($attributes)->not->toHaveKey('name')
        ->and($attributes)->toHaveKey('password');
});
