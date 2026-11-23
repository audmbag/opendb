<?php

use App\Support\ClassBundle\BundleSegments;

function bundledSymbol(string $name, bool $strict, string ...$dependencies): array
{
    return ['name' => $name, 'strict' => $strict, 'dependencies' => $dependencies];
}

it('puts strict and other files apart, each symbol after what it needs', function () {
    $segments = BundleSegments::plan([
        bundledSymbol('Contract', false),
        bundledSymbol('Base', false, 'Contract'),
        bundledSymbol('App\Service', true, 'Base'),
        bundledSymbol('Child', false, 'App\Service'),
        bundledSymbol('Helper', false),
    ]);

    expect($segments)->toBe([
        ['strict' => false, 'names' => ['Contract', 'Base', 'Helper']],
        ['strict' => true, 'names' => ['App\Service']],
        ['strict' => false, 'names' => ['Child']],
    ]);
});

it('ignores what the bundle does not hold, which the autoloader brings', function () {
    $segments = BundleSegments::plan([
        bundledSymbol('App\Model', true, 'Vendor\Model'),
        bundledSymbol('Vendor\Collection', false),
    ]);

    expect($segments)->toBe([
        ['strict' => true, 'names' => ['App\Model']],
        ['strict' => false, 'names' => ['Vendor\Collection']],
    ]);
});

it('still declares symbols whose needs no order meets', function () {
    $segments = BundleSegments::plan([
        bundledSymbol('First', false, 'Second'),
        bundledSymbol('Second', true, 'First'),
    ]);

    expect($segments)->toBe([
        ['strict' => false, 'names' => ['First']],
        ['strict' => true, 'names' => ['Second']],
    ]);
});
