<?php

namespace App;

class CarRepository
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getEntities() {
        $sql = "SELECT * FROM cars";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll();
    }

    public function find($id) {
        $sql = "SELECT * FROM cars WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        if ($car = $stmt->fetch())  {
            return $car;
        }

        return null;
    }

    public function save($car) {
        if (array_key_exists('id', $car)) {
            $sql = "UPDATE cars SET make = :make, model = :model WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':make', $car['make']);
            $stmt->bindParam(':model', $car['model']);
            $stmt->bindParam(':id', $car['id']);
            $stmt->execute();
        } else {
            $sql = "INSERT INTO cars (make, model) VALUES (:make, :model)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':make', $car['make']);
            $stmt->bindParam(':model', $car['model']);
            $stmt->execute();
        }
    }
}
