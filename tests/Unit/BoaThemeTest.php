<?php

declare(strict_types=1);

use Boa\Theme\Support\Color;
use Boa\Theme\Support\PaletteGenerator;
use Boa\Theme\Support\Presets;
use Boa\Theme\Theme;

it('parses hex colors and computes contrast', function () {
    $teal = Color::fromHex('#0f766e');
    $white = Color::fromHex('#ffffff');

    expect($teal->toHex())->toBe('#0f766e')
        ->and($teal->contrastRatio($white))->toBeGreaterThan(4.5)
        ->and($teal->contrastingInk()->toHex())->toBe('#ffffff');
});

it('expands a seed into a full palette scale', function () {
    $palette = (new PaletteGenerator)->generate('#0f766e');

    expect($palette)->toHaveKeys([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950])
        ->and($palette[50])->toStartWith('#')
        ->and($palette[950])->toStartWith('#');
});

it('builds solar-stele theme css variables', function () {
    $theme = new Theme([
        'preset' => 'solar-stele',
        'name' => 'BOA PDF',
        'tagline' => 'Your library, illuminated',
        'colors' => [],
        'fonts' => [
            'sans' => 'Source Sans 3',
            'display' => 'Cinzel',
            'google' => true,
        ],
        'radius' => [
            'sm' => '0.5rem',
            'md' => '0.75rem',
            'lg' => '1rem',
            'xl' => '1.5rem',
        ],
        'dark_mode' => false,
    ]);

    $css = $theme->cssVariables();

    expect($theme->preset())->toBe('solar-stele')
        ->and($theme->name())->toBe('BOA PDF')
        ->and($css)->toContain('--boa-brand-600:')
        ->and($css)->toContain('--boa-accent-600:')
        ->and($css)->toContain('--boa-mark-stele-mid:')
        ->and($theme->googleFontsUrl())->toContain('fonts.googleapis.com');
});

it('overrides preset colors from config seeds', function () {
    $theme = new Theme([
        'preset' => 'solar-stele',
        'colors' => [
            'brand' => '#0369a1',
            'accent' => '#ea580c',
        ],
        'fonts' => ['sans' => 'Source Sans 3', 'display' => 'Cinzel', 'google' => false],
        'radius' => [],
        'dark_mode' => false,
        'name' => 'Test',
        'tagline' => '',
    ]);

    expect($theme->color('brand', 600))->not->toBe(
        (new Theme(['preset' => 'solar-stele', 'colors' => [], 'fonts' => ['sans' => '', 'display' => '', 'google' => false], 'radius' => [], 'name' => 'x', 'tagline' => '']))->color('brand', 600)
    )->and($theme->googleFontsUrl())->toBeNull();
});

it('exposes named presets', function () {
    expect(Presets::all())->toHaveKeys(['solar-stele', 'midnight', 'coastal', 'ember'])
        ->and(Presets::get('missing'))->toBeNull();
});

it('reports accessibility pairs', function () {
    $theme = app(Theme::class);
    $report = $theme->accessibilityReport();

    expect($report)->not->toBeEmpty()
        ->and($report[0])->toHaveKeys(['pair', 'ratio', 'aa', 'aaa']);
});
