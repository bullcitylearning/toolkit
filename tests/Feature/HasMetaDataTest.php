<?php

use Bcl\Toolkit\Models\Concerns\HasMetaData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class MetaGadget extends Model
{
    use HasMetaData;

    protected $table = 'gadgets';

    protected $guarded = [];
}

class PlainGadget extends Model
{
    use HasMetaData;

    protected $table = 'plain_gadgets';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('gadgets', function ($table) {
        $table->id();
        $table->string('name');
        $table->json('metadata')->nullable();
        $table->timestamps();
    });

    Schema::create('plain_gadgets', function ($table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

it('casts metadata to schemaless attributes', function () {
    $gadget = MetaGadget::create(['name' => 'a']);

    $gadget->metadata->set('colour', 'red');
    $gadget->save();

    expect(MetaGadget::query()->sole()->metadata['colour'])->toBe('red');
});

it('folds the staged md attribute into metadata on save', function () {
    $gadget = MetaGadget::create(['name' => 'a', 'md' => ['colour' => 'red', 'size' => 'large']]);

    expect($gadget->fresh()->metadata->toArray())
        ->toBe(['colour' => 'red', 'size' => 'large'])
        ->and($gadget->getAttributes())->not->toHaveKey('md');
});

it('accepts a json string in md', function () {
    $gadget = MetaGadget::create(['name' => 'a', 'md' => json_encode(['colour' => 'blue'])]);

    expect($gadget->fresh()->metadata['colour'])->toBe('blue');
});

it('normalizes Carbon values to date strings', function () {
    $gadget = MetaGadget::create(['name' => 'a', 'md' => ['starts_on' => now()->setDate(2026, 8, 24)]]);

    expect($gadget->fresh()->metadata['starts_on'])->toBe('2026-08-24');
});

it('never stores original_filename', function () {
    $gadget = MetaGadget::create([
        'name' => 'a',
        'md' => ['original_filename' => 'secret.pdf', 'colour' => 'red'],
    ]);

    expect($gadget->fresh()->metadata->toArray())->toBe(['colour' => 'red']);
});

it('saves normally on a table without a metadata column', function () {
    PlainGadget::create(['name' => 'a']);

    expect(PlainGadget::query()->sole()->name)->toBe('a');
});

it('writes no metadata attribute when the column is absent', function () {
    $staged = null;

    // Registered after the trait's hook, so it sees what the hook did.
    PlainGadget::saving(function ($model) use (&$staged) {
        $staged = array_keys($model->getAttributes());
    });

    PlainGadget::create(['name' => 'a']);

    expect($staged)->not->toContain('metadata');
});

it('throws when md is staged on a table without a metadata column', function () {
    // Failing loudly beats the two alternatives: silently discarding what
    // the caller staged, or letting `md` reach the insert as an opaque
    // "no column named md".
    expect(fn () => (new PlainGadget(['name' => 'a', 'md' => ['colour' => 'red']]))->save())
        ->toThrow(RuntimeException::class, 'table plain_gadgets has no `metadata` column');

    expect(PlainGadget::query()->count())->toBe(0);
});

it('names the model class in the metadata-column exception', function () {
    expect(fn () => (new PlainGadget(['name' => 'a', 'md' => ['colour' => 'red']]))->save())
        ->toThrow(RuntimeException::class, PlainGadget::class);
});

it('queries through the withMetadata scope', function () {
    MetaGadget::create(['name' => 'red one', 'md' => ['colour' => 'red']]);
    MetaGadget::create(['name' => 'blue one', 'md' => ['colour' => 'blue']]);

    $reds = MetaGadget::withMetadata('colour', 'red')->get();

    expect($reds)->toHaveCount(1)
        ->and($reds->first()->name)->toBe('red one');
});

it('clears every metadata key', function () {
    $gadget = MetaGadget::create(['name' => 'a', 'md' => ['colour' => 'red', 'size' => 'large']]);

    $gadget->clearAllMetadata();
    $gadget->save();

    expect($gadget->fresh()->metadata->toArray())->toBe([]);
});
