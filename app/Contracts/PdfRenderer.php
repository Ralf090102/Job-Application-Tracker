<?php

namespace App\Contracts;

/**
 * Contract for rendering a Markdown file to PDF. An interface (not a
 * direct call to ProteusPdfRenderer) so tests can bind a fake instead of
 * shelling out to a real Proteus process.
 */
interface PdfRenderer
{
    /**
     * @throws \App\Exceptions\PdfRenderException
     */
    public function render(string $markdownPath, string $pdfPath): void;
}
