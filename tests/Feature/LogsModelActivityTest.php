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

beforeEach(function () {
    // The package publishes this migration rather than running it, so
    // the suite executes the vendor stub directly — no schema to drift.
    (require __DIR__.'/../../vendor/spatie/laravel-activitylog/database/migrations/create_activity_log_table.php.stub')->up();

    Schema::create('widgets', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('password')->nullable();
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
