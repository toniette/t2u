<?php

namespace Marcianos\Estudos\Entities;

use Marcianos\Estudos\Collections\ModuleCollection;

class Course
{
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly string $category,
        private Instructor $instructor,
        private ModuleCollection $modules,
    ) {}

}