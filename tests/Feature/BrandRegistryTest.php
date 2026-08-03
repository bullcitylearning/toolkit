<?php

use Bcl\Toolkit\Brand\Brand;
use Illuminate\Support\Facades\Blade;

it('merges the brand registry into config', function () {
    expect(config('brands.bcl.name'))->toBe('BCL')
        ->and(config('brands.bcb.name'))->toBe('Bull City Blue');
});

it('exposes brand identity through the enum', function () {
    expect(Brand::Bcl->displayName())->toBe('BCL')
        ->and(Brand::Bcl->domain())->toBe('pulse.bcltraining.com')
        ->and(Brand::Bcl->fromAddress())->toBe('surveys@bcltraining.com')
        ->and(Brand::Bcl->baseDomain())->toBe('bcltraining.net')
        ->and(Brand::Bcl->teamEmailDomain())->toBe('bcltraining.com')
        ->and(Brand::Bcb->baseDomain())->toBeNull()
        ->and(Brand::Bcl->theme()['primary'])->toBe('#002139')
        ->and(Brand::Bcb->theme()['primary'])->toBe('#00618D');
});

it('builds urls on the brand domain', function () {
    expect(Brand::Bcl->url('s/abc'))->toBe('https://pulse.bcltraining.com/s/abc')
        ->and(Brand::Bcb->url())->toBe('https://pulse.bullcityblue.com/');
});

it('resolves a brand from a host header, defaulting to BCL', function () {
    expect(Brand::fromHost('PULSE.BULLCITYBLUE.COM'))->toBe(Brand::Bcb)
        ->and(Brand::fromHost('unknown.example'))->toBe(Brand::Bcl)
        ->and(Brand::fromHost(null))->toBe(Brand::Bcl);
});

it('registers default mailers for both brands', function () {
    expect(config('mail.mailers.bcl'))->not->toBeNull()
        ->and(config('mail.mailers.bcb'))->not->toBeNull()
        ->and(config('mail.mailers.bcl.transport'))->toBe(config('mail.mailers.smtp.transport'));
});

it('renders the themed mail layout', function () {
    $html = Blade::render(
        "@extends('bcl-toolkit::mail.layout') @section('content') Hello from the body. @endsection",
        ['brand' => Brand::Bcl],
    );

    expect($html)
        ->toContain('Hello from the body.')
        ->toContain('#002139')
        ->toContain('logo-white-orange-underline.png')
        ->toContain('600 Park Offices Drive');
});
