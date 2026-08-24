<?php

use Bcl\Toolkit\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;

class TranslatedPost extends Model
{
    use HasTranslations;

    protected $table = 'posts';

    protected $guarded = [];

    public array $translatable = ['title'];
}

beforeEach(function () {
    Schema::create('posts', function ($table) {
        $table->id();
        $table->json('title');
        $table->timestamps();
    });

    App::setLocale('en');
});

it('emits the active locale string in toArray instead of the locale map', function () {
    $post = TranslatedPost::create(['title' => ['en' => 'Hello', 'es' => 'Hola']]);

    expect($post->toArray()['title'])->toBe('Hello');

    App::setLocale('es');

    expect($post->toArray()['title'])->toBe('Hola');
});

it('prefers an explicit per-instance locale', function () {
    $post = TranslatedPost::create(['title' => ['en' => 'Hello', 'es' => 'Hola']]);

    $post->setLocale('es');

    expect($post->getLocale())->toBe('es')
        ->and($post->toArray()['title'])->toBe('Hola');
});

it('reads the locale from the session when there is one', function () {
    session(['locale' => 'es']);
    request()->setLaravelSession(app('session.store'));

    $post = TranslatedPost::create(['title' => ['en' => 'Hello', 'es' => 'Hola']]);

    expect($post->getLocale())->toBe('es')
        ->and($post->toArray()['title'])->toBe('Hola');
});

it('falls back to the app locale outside a session', function () {
    App::setLocale('es');

    $post = TranslatedPost::create(['title' => ['en' => 'Hello', 'es' => 'Hola']]);

    expect(request()->hasSession())->toBeFalse()
        ->and($post->getLocale())->toBe('es')
        ->and($post->toArray()['title'])->toBe('Hola');
});

it('still exposes the raw translations through the vendor accessors', function () {
    $post = TranslatedPost::create(['title' => ['en' => 'Hello', 'es' => 'Hola']]);

    expect($post->getTranslations('title'))->toBe(['en' => 'Hello', 'es' => 'Hola'])
        ->and($post->getTranslation('title', 'es'))->toBe('Hola');
});
