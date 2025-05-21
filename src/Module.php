<?php

namespace Marcianos\Estudos;

class Module
{
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly string $category,
    )
    {
    }
}