<?php

namespace Marcianos\Estudos\Entities;

class Enrollment
{
    public function __construct(
        private Student $student,
        private Course $course,
    ) {}
}