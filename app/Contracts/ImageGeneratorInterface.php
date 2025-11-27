<?php

namespace App\Contracts;

use App\Models\Persona;

interface ImageGeneratorInterface
{
    /**
     * Generate an image and return its public URL.
     */
    public function generate(string $prompt, Persona $persona): string;
}
