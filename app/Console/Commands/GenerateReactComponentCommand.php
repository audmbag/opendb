<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('rc {name : The name of the React component}')]
#[Description('Generate a new React component')]
class GenerateReactComponentCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $content = $this->getContent();

        $componentName = Str::kebab($this->argument('name')).'.tsx';

        if (! is_dir(resource_path('js/components'))) {
            mkdir(resource_path('js/components'), 0755, true);
        }

        $this->createSubDirectoryIfNotExists($fullPath = resource_path("js/components/$componentName"));

        file_put_contents($fullPath, $content);

        $this->info("React component created at: $fullPath");
    }

    public function createSubDirectoryIfNotExists(string $fullFilePath): bool
    {
        $directory = dirname($fullFilePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return true;
    }

    public function getContent(): string
    {
        $componentName = Str::studly(basename($this->argument('name')));

        return <<<EOT
import {  } from 'react';

type Props = {

};

export default function $componentName({  }: Props) {
    return (
        <div>

        </div>
    );
}
EOT;
    }
}
