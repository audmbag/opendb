<?php

namespace Fixture;

abstract class Base implements Contract
{
    public function make(): Contract
    {
        return new Product;
    }

    public function folder(): string
    {
        return __DIR__;
    }
}
