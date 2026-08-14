<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Fails when the file is an image larger than $maxKb. Non-image files pass (a separate `max:`
 * rule caps those). Used where a field accepts both images and PDFs — images are held to a
 * tight limit (and compressed client-side first via resources/js/image-compress.js), while
 * PDFs get a looser cap.
 */
class ImageUnderSize implements ValidationRule
{
    public function __construct(private int $maxKb = 2048)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof UploadedFile
            && str_starts_with((string) $value->getMimeType(), 'image/')
            && $value->getSize() > $this->maxKb * 1024) {
            $fail('Images must be ' . rtrim(rtrim(number_format($this->maxKb / 1024, 1), '0'), '.') . ' MB or smaller.');
        }
    }
}
