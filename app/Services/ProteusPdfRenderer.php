<?php

namespace App\Services;

use App\Contracts\PdfRenderer;
use App\Exceptions\PdfRenderException;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\Process;

/**
 * Shells out to the Proteus CLI (D:\GitHub\Proteus, `uv tool install .`) to
 * render Markdown to PDF — no new PDF-rendering dependency needed
 * (JAT-Roadmap-AutoApply.md Phase 3). `md -> pdf` is Proteus's own chained
 * Pandoc-then-LibreOffice conversion; confirmed available via
 * `proteus doctor` during this phase's build.
 */
class ProteusPdfRenderer implements PdfRenderer
{
    public function render(string $markdownPath, string $pdfPath): void
    {
        $bin = config('services.proteus.bin');

        $process = new Process([$bin, 'convert', $markdownPath, '--to', 'pdf', '--output', $pdfPath]);
        $process->setTimeout(120);

        try {
            $process->run();
        } catch (ProcessException $e) {
            throw new PdfRenderException("Couldn't run the Proteus CLI ({$bin}) — is it installed and on PATH?", previous: $e);
        }

        if (! $process->isSuccessful()) {
            throw new PdfRenderException(
                "Proteus exited with an error: ".trim($process->getErrorOutput() ?: $process->getOutput()),
            );
        }

        if (! is_file($pdfPath)) {
            throw new PdfRenderException("Proteus reported success but {$pdfPath} doesn't exist.");
        }
    }
}
