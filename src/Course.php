<?php

namespace Marcianos\Estudos;

class Course
{
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly string $category,
        private Instructor $instructor,
    )
    {
    }

}