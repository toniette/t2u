<?php

namespace Marcianos\Estudos\Collections;

use InvalidArgumentException;
use SplObjectStorage;

abstract class Collection extends SplObjectStorage
{
    private ?string $type;

    private array $items = [];

    public function __construct()
    {
        if (empty($this->type)) {
            $this->guessType();
        }
    }

    private function guessType(): void
    {
        $className = $this::class;

        $className = str_replace(__NAMESPACE__, '', $className);
        $className = str_replace('Collection', '', $className);

        if (!class_exists($className)) {
            throw new InvalidArgumentException("Class {$className} does not exist");
        }

        $this->type = $className;
    }
}