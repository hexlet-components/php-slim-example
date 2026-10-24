<?php

declare(strict_types=1);

namespace App;

class CarValidator
{
    public function validate(array $car): array
    {
        $errors = [];
        if (($car['make'] ?? '') === '') {
            $errors['make'] = 'Make can not be empty';
        }

        if (($car['model'] ?? '') === '') {
            $errors['model'] = 'Model can not be empty';
        }

        return $errors;
    }
}
