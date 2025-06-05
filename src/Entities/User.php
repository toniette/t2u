<?php

namespace Marcianos\Estudos\Entities;

abstract class User
{
    public function __construct(
        private readonly string $name,
        private readonly string $document,
        private string $email,
        private string $password
    ) {}
}