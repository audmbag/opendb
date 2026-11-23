<?php

use App\Support\ClassBundle\DeclaredSymbols;
use Symfony\Component\Process\Process;

it('lists each symbol with its parents, interfaces and the classes its methods made PHP load', function () {
    $fixture = realpath(__DIR__.'/../../../Fixtures/class-bundle');
    $script = new ReflectionClass(DeclaredSymbols::class)->getFileName();

    $output = new Process([PHP_BINARY, '-r', <<<PHP
        require '{$script}';
        require '{$fixture}/vendor/autoload.php';
        App\Support\ClassBundle\DeclaredSymbols::track();
        new Fixture\Child;
        echo json_encode(App\Support\ClassBundle\DeclaredSymbols::describe());
        PHP])->mustRun()->getOutput();

    expect(array_column(json_decode($output, true), null, 'name'))->toEqual([
        'Fixture\Contract' => ['name' => 'Fixture\Contract', 'kind' => 'interface', 'file' => $fixture.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Contract.php', 'dependencies' => [], 'internalAncestors' => []],
        'Fixture\Base' => ['name' => 'Fixture\Base', 'kind' => 'class', 'file' => $fixture.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Base.php', 'dependencies' => ['Fixture\Contract'], 'internalAncestors' => []],
        'Fixture\Product' => ['name' => 'Fixture\Product', 'kind' => 'class', 'file' => $fixture.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Product.php', 'dependencies' => ['Fixture\Contract'], 'internalAncestors' => []],
        'Fixture\Child' => ['name' => 'Fixture\Child', 'kind' => 'class', 'file' => $fixture.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Child.php', 'dependencies' => ['Fixture\Base', 'Fixture\Contract', 'Fixture\Product'], 'internalAncestors' => []],
    ]);
});
