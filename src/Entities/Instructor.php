<?php

namespace Marcianos\Estudos\Entities;

use Marcianos\Estudos\Collections\ModuleCollection;

class Instructor extends User
{
    public function createCourse(
        string $name, string $description,
        string $category, ModuleCollection $modules
    ): Course
    {
        return new Course($name, $description, $category, $this, $modules);
    }
}