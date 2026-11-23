<?php

declare(strict_types=1);

namespace Fixture;

final class Product implements Contract
{
    public function make(): Contract
    {
        return $this;
    }
}
