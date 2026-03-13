<?php

namespace LokyHelpMe\Services;

use Illuminate\Support\Str;

class InputValidator
{
    public function validateNotEmpty(?string $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    public function validateClassName(?string $name): bool
    {
        return is_string($name) && preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) === 1;
    }

    public function validateTableName(?string $name): bool
    {
        return is_string($name) && preg_match('/^[a-z][a-z0-9_]*$/', $name) === 1;
    }

    public function validateColumnName(?string $name): bool
    {
        return $this->validateTableName($name);
    }

    public function normalizeClassName(?string $name): string
    {
        if (! is_string($name)) {
            return '';
        }

        $ascii = Str::ascii(trim($name));
        $clean = preg_replace('/[^A-Za-z0-9]+/', ' ', $ascii);

        if (! is_string($clean)) {
            return '';
        }

        return Str::studly($clean);
    }
}
