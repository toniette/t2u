<?php

namespace Marcianos\Estudos;

class Student extends User
{
    private array $courses = [];
    
    public function getCourses()
    {
        return $this->courses;
    }

    public function enroll(Course)
    {
    }
}