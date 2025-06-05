<?php

namespace Marcianos\Estudos\Entities;

use Marcianos\Estudos\Collections\ModuleCollection;

class Course
{
    public function __construct(
        public string $name,
        public readonly string $description,
        public string $category,
        public Instructor $instructor,
        public ModuleCollection $modules,
    ) {}
}