<?php

namespace App\Support;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Reads the printed text on an ID photo with Tesseract OCR (a free program installed beside AGAPAY: the Windows
 * installer on the office PC, the tesseract-ocr package on the cloud copy). Without it, AGAPAY still works and the
 * Administrator compares the ID by eye; the checks then say "not read".
 */
class IdTextReader
{
    private ?bool $available = null;

    public function available(): bool
    {
        if (! config('agapay.ocr.enabled', true)) {
            return false;
        }

        return $this->available ??= $this->binary() !== null;
    }

    /** The text on the photo, or null when OCR is not installed or the photo cannot be read. */
    public function read(string $path): ?string
    {
        $binary = $this->available() ? $this->binary() : null;
        if ($binary === null || ! is_file($path)) {
            return null;
        }

        try {
            $process = new Process([$binary, $path, 'stdout', '-l', 'eng', '--psm', '6']);
            $process->setTimeout((float) config('agapay.ocr.timeout', 30));
            $process->run();

            return $process->isSuccessful() ? trim($process->getOutput()) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function binary(): ?string
    {
        $configured = (string) config('agapay.ocr.binary', '');
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        // On PATH, or the Windows installer's default folder.
        return (new ExecutableFinder)->find('tesseract', null, ['C:\\Program Files\\Tesseract-OCR', 'C:\\Program Files (x86)\\Tesseract-OCR']);
    }
}
