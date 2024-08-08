<?php

namespace App;

class CarValidator
{
    public function validate(array $car): array
    {
        $errors = [];
        if (empty($car['make'])) {
            $errors['make'] = "Make can not be empty";
        }

        if (empty($car['model'])) {
            $errors['model'] = "Model can not be empty";
        }

        return $errors;
    }
}
