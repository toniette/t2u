<?php

namespace Marcianos\Estudos\Entities;

use Marcianos\Estudos\Collections\EnrollmentCollection;

class Student extends User
{
    private EnrollmentCollection $enrollments;

    public function __construct(string $name, string $document, string $email, string $password)
    {
        parent::__construct($name, $document, $email, $password);
        $this->enrollments = new EnrollmentCollection();
    }

    public function enroll(Course $course)
    {
        $enrollment = new Enrollment($this, $course);
        $this->enrollments->add($enrollment);
    }
}