<?php

namespace Marcianos\Estudos;

abstract class Collection
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
        $className = get_class($this);
        $className = str_replace('Collection', '', $className);

        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Class {$className} does not exist");
        }

        $this->type = $className;
    }

    public function add($item): void
    {
        if (!$item instanceof $this->type) {
            throw new \InvalidArgumentException("Item must be of type {$this->type}");
        }

        $this->items[] = $item;
    }

    public function getItems(): array
    {
        return $this->items;
    }
}