<?php

namespace Marcianos\Estudos;

abstract class Lesson
{
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly int $duration,
    )
    {
    }
}