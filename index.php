<?php

use Marcianos\Estudos\Collections\EnrollmentCollection;
use Marcianos\Estudos\Collections\ModuleCollection;
use Marcianos\Estudos\Entities\Article;
use Marcianos\Estudos\Entities\Course;
use Marcianos\Estudos\Entities\Instructor;
use Marcianos\Estudos\Entities\Module;
use Marcianos\Estudos\Entities\Student;
use Marcianos\Estudos\Entities\Video;

require_once 'vendor/autoload.php';

$instructor = new Instructor(
    'João da Silva',
    '12345678900',
    'joao@silva.com',
    'senha123',
);

$module = new Module();
$lessonA = new Video('Aula 1: Introdução ao Curso', 'https://example.com/video1.mp4', 15);
$lessonB = new Video('Aula 2: Conceitos Básicos', 'https://example.com/video2.mp4', 20);
$lessonC = new Article('Aula 3: Avançando no Conteúdo', 'Abóborinha', 25);
$lessonD = new Article('Aula 3: Avançando no Conteúdo', 'Cágado', 30);

$module->attachAll($lessonA, $lessonB, $lessonC, $lessonD);

$modules = new ModuleCollection();
$modules->attach($module);

$course = new Course(
    'T2U',
    'Curso de Testes para Universitários',
    'Tecnologia',
    $instructor,
    $modules
);

$student = new Student(
    'Maria Oliveira',
    '98765432100',
    'maria@oliveira.com',
    'senha456'
);

$enrollments = new EnrollmentCollection();
$enrollmentA = $student->enroll($course);
$enrollmentB = $student->enroll($course);

$enrollments->attach($enrollmentA);

var_dump($enrollments);