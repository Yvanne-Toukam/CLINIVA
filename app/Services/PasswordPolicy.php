<?php

declare(strict_types=1);

namespace Cliniva\Services;

final class PasswordPolicy
{
    /** @return array<int, string> */
    public function errors(string $password, string $confirmation): array
    {
        $errors = [];

        if ($password !== $confirmation) {
            $errors[] = 'Les deux mots de passe ne correspondent pas.';
        }

        if (strlen($password) < 10) {
            $errors[] = 'Le mot de passe doit contenir au moins 10 caracteres.';
        }

        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir majuscule, minuscule, chiffre et symbole.';
        }

        return $errors;
    }
}
