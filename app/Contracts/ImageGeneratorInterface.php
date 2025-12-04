<?php

namespace App\Contracts;

use App\Models\Persona;

interface ImageGeneratorInterface
{
    /**
     * Generate an image and return its public URL.
     */
    public function generate(string $prompt, Persona $persona): string;

    /**
     * Edit an existing image with a prompt and return the edited image's public URL.
     */
    public function editImage(string $referenceImageUrl, string $prompt, Persona $persona): string;
}
