<?php

declare(strict_types=1);

namespace Fixture;

// Its method returns Product where its parent returns Contract: declaring it loads Product, to compare them.
final class Child extends Base
{
    public function make(): Product
    {
        return new Product;
    }
}
