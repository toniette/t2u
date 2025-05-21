<?php

namespace Marcianos\Estudos;

abstract class User
{

    public function __construct(
        private readonly string $name,
        private readonly string $document,
        private string $email,
        private string $password
    )
    {
    }
}