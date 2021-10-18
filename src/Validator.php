<?php

namespace App;

class Validator
{
    public function validate(array $user): array
    {
        $errors = [];
        if (mb_strlen($user['nickname']) < 4) {
            $errors['nickname'] = "Nickname must be grater than 4 characters";
        }

        return $errors;
    }
}
