<?php

use Bcl\Toolkit\Brand\Brand;
use Bcl\Toolkit\ToolkitServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;

beforeEach(function () {
    config([
        'brands.default' => 'acme',
        'brands.registry' => [
            'acme' => [
                'name' => 'Acme',
                'domain' => 'app.acme.test',
                'from_address' => 'hello@acme.test',
                'from_name' => 'Acme',
                'mailer' => null,
                'website' => 'https://www.acme.test',
                'address' => '1 Example Way, Springfield',
                'base_domain' => 'acme-sites.test',
                'team_email_domain' => 'acme.test',
                'logo_white' => 'brands/acme/logo-white.svg',
                'logo_white_png' => 'brands/acme/logo-white.png',
                'theme' => [
                    'primary' => '#112233',
                    'accent' => '#ff5500',
                    'link' => '#0066cc',
                    'link_dark' => '#004488',
                    'highlight' => '#eecc00',
                    'surface' => '#eeeeee',
                    'heading_font' => "'Poppins', sans-serif",
                    'body_font' => "'Roboto', sans-serif",
                    'font_head' => '',
                ],
            ],
            'zenith' => [
                'name' => 'Zenith',
                'domain' => 'app.zenith.test',
                'from_address' => 'hello@zenith.test',
                'from_name' => 'Zenith',
                'theme' => ['primary' => '#445566'],
            ],
        ],
    ]);
});

it('ships an empty registry that leaves the brand machinery inert', function () {
    config(['brands.registry' => [], 'brands.default' => null]);

    expect(Brand::all())->toBe([])
        ->and(Brand::tryFrom('acme'))->toBeNull()
        ->and(Brand::default())->toBeNull()
        ->and(Brand::fromHost('anything.test'))->toBeNull();
});

it('resolves registered brands as flyweight instances', function () {
    $brand = Brand::from('acme');

    expect($brand->value)->toBe('acme')
        ->and(Brand::from('acme'))->toBe($brand)
        ->and(Brand::tryFrom('nope'))->toBeNull()
        ->and(Brand::all())->toHaveCount(2)
        ->and(Brand::cases())->toEqual(Brand::all());
});

it('throws an enum-style error for unregistered slugs', function () {
    Brand::from('nope');
})->throws(ValueError::class, 'not a registered brand');

it('exposes brand identity through the accessors', function () {
    $brand = Brand::from('acme');

    expect($brand->displayName())->toBe('Acme')
        ->and($brand->domain())->toBe('app.acme.test')
        ->and($brand->fromAddress())->toBe('hello@acme.test')
        ->and($brand->baseDomain())->toBe('acme-sites.test')
        ->and($brand->teamEmailDomain())->toBe('acme.test')
        ->and(Brand::from('zenith')->baseDomain())->toBeNull()
        ->and($brand->theme()['primary'])->toBe('#112233');
});

it('builds urls on the brand domain', function () {
    expect(Brand::from('acme')->url('s/abc'))->toBe('https://app.acme.test/s/abc')
        ->and(Brand::from('zenith')->url())->toBe('https://app.zenith.test/');
});

it('resolves a brand from a host header, falling back to the default', function () {
    expect(Brand::fromHost('APP.ZENITH.TEST'))->toBe(Brand::from('zenith'))
        ->and(Brand::fromHost('unknown.example'))->toBe(Brand::from('acme'))
        ->and(Brand::fromHost(null))->toBe(Brand::from('acme'));
});

it('casts to and from the slug on eloquent models', function () {
    $model = new class extends Model
    {
        protected $guarded = [];

        protected function casts(): array
        {
            return ['brand' => Brand::class];
        }
    };

    $instance = $model->newInstance(['brand' => 'acme']);

    expect($instance->brand)->toBe(Brand::from('acme'))
        ->and($instance->getAttributes()['brand'])->toBe('acme');

    $instance->brand = Brand::from('zenith');

    expect($instance->getAttributes()['brand'])->toBe('zenith');
});

it('registers default mailers for registered brands', function () {
    // The provider registered mailers at boot from the (empty) shipped
    // registry; re-run against the fixture registry.
    (fn () => $this->registerBrandMailerDefaults())->call(app()->getProvider(ToolkitServiceProvider::class));

    expect(config('mail.mailers.acme'))->not->toBeNull()
        ->and(config('mail.mailers.zenith.transport'))->toBe(config('mail.mailers.smtp.transport'));
});

it('renders the themed mail layout', function () {
    $html = Blade::render(
        "@extends('bcl-toolkit::mail.layout') @section('content') Hello from the body. @endsection",
        ['brand' => Brand::from('acme')],
    );

    expect($html)
        ->toContain('Hello from the body.')
        ->toContain('#112233')
        ->toContain('logo-white.png')
        ->toContain('1 Example Way, Springfield');
});

it('serializes to its slug in json', function () {
    expect(json_encode(['brand' => Brand::from('acme')]))->toBe('{"brand":"acme"}');
});
