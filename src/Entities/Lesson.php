<?php

namespace Marcianos\Estudos\Entities;

abstract class Lesson
{
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly int $duration,
    ) {}
}