<?php

namespace App\Console\Commands\Settings;

use App\Models\Project;

/**
 * A section of the settings of a project.
 */
abstract class ProjectSection extends Section
{
    abstract public function run(Project $project): void;
}
