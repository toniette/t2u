<?php

namespace Marcianos\Estudos\Collections;

use InvalidArgumentException;
use ReturnTypeWillChange;
use SplObjectStorage;

abstract class Collection extends SplObjectStorage
{
    protected ?string $type;

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

        $className = 'Marcianos\\Estudos\\Entities' . $className;
        if (!class_exists($className)) {
            throw new InvalidArgumentException("Class {$className} does not exist");
        }

        $this->type = $className;
    }

    public function attach(object $object, mixed $info = null): void
    {
        if (!$object instanceof $this->type) {
            throw new InvalidArgumentException(
                "Object must be an instance of $this->type, " . $object::class . " given"
            );
        }

        parent::attach($object, $info);
    }

    public function attachAll(object ...$objects): void
    {
        foreach ($objects as $object) {
            $this->attach($object);
        }
    }

    public function offsetSet($object, $info = null): void
    {
        $this->attach($object, $info);
    }

    #[ReturnTypeWillChange]
    public function addAll(SplObjectStorage $storage): void
    {
        foreach ($storage as $object) {
            $this->attach($object, $storage[$object]);
        }
    }
}