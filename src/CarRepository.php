<?php

namespace App;

class CarRepository
{
    private $conn;

    public function __construct(\PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getEntities() {
        $cars = [];
        $sql = "SELECT * FROM cars";
        $stmt = $this->conn->query($sql);

        while ($row = $stmt->fetch()) {
            $car = new Car(
                $row['make'],
                $row['model']
            );
            $car->setId($row['id']);
            $cars[] = $car;
        }

        return $cars;
    }

    public function find($id) {
        $sql = "SELECT * FROM cars WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        if ($row = $stmt->fetch())  {
            $car = new Car($row['make'], $row['model']);
            $car->setId($row['id']);
            return $car;
        }

        return null;
    }

    public function save($car) {
        if (is_null($car->getId())) {
            $sql = "INSERT INTO cars (make, model) VALUES (:make, :model)";
            $stmt = $this->conn->prepare($sql);
            $make = $car->getMake();
            $model = $car->getModel();
            $stmt->bindParam(':make', $make);
            $stmt->bindParam(':model', $model);
            $stmt->execute();
            $id = (int) $this->conn->lastInsertId();
            $car->setId($id);
        } else {
            $sql = "UPDATE cars SET make = :make, model = :model WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $id = $car->getId();
            $make = $car->getMake();
            $model = $car->getModel();
            $stmt->bindParam(':make', $make);
            $stmt->bindParam(':model', $model);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
        }
    }
}
