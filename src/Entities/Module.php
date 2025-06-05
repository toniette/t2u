<?php

namespace Marcianos\Estudos\Entities;

use Marcianos\Estudos\Collections\Collection;

class Module extends Collection
{
    public function __construct()
    {
        $this->type = Lesson::class;
        parent::__construct();
    }

}