<?php

namespace Marcianos\Estudos\Entities;

use Exception;
use Marcianos\Estudos\Collections\CourseCollection;

class Student extends User
{
    private CourseCollection $courses;

    public function __construct(string $name, string $document, string $email, string $password)
    {
        parent::__construct($name, $document, $email, $password);
        $this->courses = new CourseCollection();
    }

    /**
     * @throws Exception
     */
    public function enroll(Course $course): Enrollment
    {
        if ($this->courses->contains($course)) {
            throw new Exception("You are already enrolled in this course.");
        }

        $this->courses->attach($course);
        return new Enrollment($this, $course);
    }
}