<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class PdfConversionException extends RuntimeException
{
    public const MISSING_TOOL = 10;

    public static function missingTool(string $tool, string $purpose): self
    {
        return new self(
            "PDF conversion cannot {$purpose} because {$tool} is not installed or configured.",
            self::MISSING_TOOL,
        );
    }

    public static function failed(string $stage, Throwable $previous): self
    {
        return new self(
            "PDF conversion failed during {$stage}: ".self::cleanMessage($previous->getMessage()),
            previous: $previous,
        );
    }

    private static function cleanMessage(string $message): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');

        return $message !== '' ? $message : 'The conversion tool did not provide an error message.';
    }
}
