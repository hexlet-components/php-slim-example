<?php

namespace App;

class Car
{
    private ?int $id = null;
    private ?string $make = null;
    private ?string $model = null;

    public static function fromArray(array $carData): Car
    {
        [$make, $model] = $carData;
        $car = new Car();
        $car->setMake($make);
        $car->setModel($model);
        return $car;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMake(): ?string
    {
        return $this->make;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setMake(string $make): void
    {
        $this->make = $make;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    public function exists(): bool
    {
        return !is_null($this->getId());
    }
}
