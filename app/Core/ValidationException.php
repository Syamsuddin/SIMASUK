<?php

declare(strict_types=1);

namespace App\Core;

final class ValidationException extends \RuntimeException
{
    /** @param array<string,string> $errors field => pesan */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Isian belum valid.');
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
