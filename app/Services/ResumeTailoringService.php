<?php

namespace App\Services;

use App\Contracts\PdfRenderer;
use App\Contracts\ResumeTailor;
use App\Enums\AutoApplyCandidateStatus;
use App\Exceptions\PdfRenderException;
use App\Exceptions\ResumeTailoringException;
use App\Models\AutoApplyCandidate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Orchestrates Phase 3: pick a resume variant, tailor it against a
 * candidate's posting, render it to PDF via Proteus. Reads source material
 * from the Eru vault (ERU_VAULT_PATH) — never writes back to it; output
 * goes to this project's own storage
 * (storage/app/private/auto-apply/{candidate_id}/), never the canonical
 * Eru resumes (JAT-Roadmap-AutoApply.md Phase 3).
 *
 * Deliberately never throws — a tailoring or render failure just leaves
 * the candidate at whatever status it reached (matched/tweaked), logged,
 * rather than aborting the ingest batch it's called from.
 */
class ResumeTailoringService
{
    /**
     * Known resume variants: key => vault-relative path, under
     * 02-Areas/Career/Resumes/ in the Eru vault. Each note's own "## Goal"
     * section is used as the short summary for variant selection — see
     * extractGoalSummary().
     */
    private const VARIANTS = [
        'SD' => 'Resume-SD.md',
        'PE' => 'Resume-PE.md',
        'PE2' => 'Resume-PE2.md',
    ];

    private const PORTFOLIO_PATH = '02-Areas/Career/Project-Portfolio.md';

    public function __construct(
        private ResumeTailor $tailor,
        private PdfRenderer $renderer,
    ) {}

    public function process(AutoApplyCandidate $candidate): void
    {
        $vaultPath = config('services.eru.vault_path');

        if (! $vaultPath) {
            Log::warning('Resume tailoring skipped: ERU_VAULT_PATH is not configured.');

            return;
        }

        try {
            $variantSummaries = $this->loadVariantSummaries($vaultPath);
            $selection = $this->tailor->selectVariant(
                $candidate->role,
                $candidate->company,
                $candidate->posting_text ?? '',
                $variantSummaries,
            );

            $fullResumeMarkdown = $this->readVaultFile($vaultPath, '02-Areas/Career/Resumes/'.self::VARIANTS[$selection['variant']]);
            $fullPortfolioMarkdown = $this->readVaultFile($vaultPath, self::PORTFOLIO_PATH);

            // Trimmed, not the full raw notes — a real live test measured
            // the full-note prompt (~9k tokens) failing to complete even at
            // a 10-minute timeout on local CPU inference. Cutting to just
            // the actual resume content (Experience/Projects/Skills, no
            // Status/Goal/TODO noise) and only the portfolio sections for
            // projects the resume already names cuts the prompt to a
            // fraction of that size — also removes the risk of the model
            // picking up meta-commentary as if it were resume content.
            $resumeSections = $this->extractCoreResumeSections($fullResumeMarkdown);
            $portfolioMarkdown = $this->extractRelevantPortfolioSections($fullPortfolioMarkdown, $resumeSections);

            // Skills is handled separately from the LLM call entirely — a
            // real live test produced fabricated skills (Azure, Angular,
            // Power Automate, GraphQL/Relay — none present in either
            // source) that the model had pulled from the target posting's
            // own requirements rather than the candidate's actual skills.
            // Experience/Projects, by contrast, were fully truthful in that
            // same test — a bounded "select from these existing bullets"
            // task, not open-ended like writing a skills line. So: only
            // Experience/Projects go through the LLM; Skills is spliced in
            // afterward verbatim, straight from the source, no generation.
            [$experienceAndProjects, $skillsSection] = $this->splitOffSkills($resumeSections);

            $tailoredExperienceAndProjects = $this->tailor->tailor(
                $candidate->role,
                $candidate->company,
                $candidate->posting_text ?? '',
                $experienceAndProjects,
                $portfolioMarkdown,
            );

            $tailored = trim($tailoredExperienceAndProjects)."\n\n".$skillsSection;
        } catch (ResumeTailoringException|RuntimeException $e) {
            Log::warning('Resume tailoring failed', ['candidate_id' => $candidate->id, 'error' => $e->getMessage()]);

            return;
        }

        $mdRelativePath = "auto-apply/{$candidate->id}/resume.md";
        Storage::disk('local')->put($mdRelativePath, $tailored);

        $candidate->update([
            'resume_variant' => $selection['variant'],
            'resume_variant_reason' => $selection['reason'],
            'status' => AutoApplyCandidateStatus::Tweaked,
        ]);

        $mdAbsolutePath = Storage::disk('local')->path($mdRelativePath);
        $pdfAbsolutePath = Storage::disk('local')->path("auto-apply/{$candidate->id}/resume.pdf");

        try {
            $this->renderer->render($mdAbsolutePath, $pdfAbsolutePath);
        } catch (PdfRenderException $e) {
            Log::warning('Resume PDF render failed', ['candidate_id' => $candidate->id, 'error' => $e->getMessage()]);

            return;
        }

        $candidate->update([
            'tailored_resume_path' => $pdfAbsolutePath,
            'status' => AutoApplyCandidateStatus::ReadyForReview,
        ]);
    }

    /**
     * @return array<string, string> variant key => its note's "## Goal" section text
     */
    private function loadVariantSummaries(string $vaultPath): array
    {
        $summaries = [];

        foreach (self::VARIANTS as $key => $filename) {
            $content = $this->readVaultFile($vaultPath, '02-Areas/Career/Resumes/'.$filename);
            $summaries[$key] = $this->extractGoalSummary($content) ?? "Resume variant {$key}.";
        }

        return $summaries;
    }

    /**
     * Pulls just the "## Goal" section out of a resume note — each of the
     * three notes states its own target audience there in a clean single
     * paragraph, unlike the rest of the note which mixes in Status/TODO
     * meta-commentary. Keeps the variant-selection prompt small and clean.
     */
    private function extractGoalSummary(string $noteContent): ?string
    {
        if (! preg_match('/##\s*Goal\s*\n(.*?)(?=\n##\s|\z)/s', $noteContent, $matches)) {
            return null;
        }

        return trim($matches[1]) ?: null;
    }

    /**
     * Cuts a resume note down to its "### Experience" through "## Skills"
     * block — the actual resume content — dropping everything before it
     * (Status/Goal/Framing meta-commentary) and after it (Open
     * questions/TODO, Related). Cut/kept annotations for individual
     * bullets (e.g. "cut from the final 1-page PDF") live inline within
     * this block itself, so they're preserved — only the surrounding
     * planning-note scaffolding is removed.
     */
    private function extractCoreResumeSections(string $noteContent): string
    {
        if (preg_match('/###\s*Experience.*?(?=\n##\s*(?:Open questions|Related)|\z)/is', $noteContent, $matches)) {
            return trim($matches[0]);
        }

        // Fallback: the expected structure wasn't found — send the whole
        // note rather than silently sending nothing to the LLM.
        return $noteContent;
    }

    /**
     * Cuts the portfolio down to only the project sections the (already
     * trimmed) resume content actually names, plus the Contact section
     * (needed for the resume's header). Each portfolio project is its own
     * "### Name — ..." block; a block is kept if its name appears anywhere
     * in the resume sections.
     */
    private function extractRelevantPortfolioSections(string $portfolioContent, string $resumeSections): string
    {
        $kept = [];

        if (preg_match('/##\s*7\.\s*Contact.*?(?=\n##\s|\z)/is', $portfolioContent, $matches)) {
            $kept[] = trim($matches[0]);
        }

        preg_match_all('/###\s+(.+?)\n(.*?)(?=\n###\s|\n##\s|\z)/s', $portfolioContent, $projectMatches, PREG_SET_ORDER);

        foreach ($projectMatches as $match) {
            // A project header reads like "WiQAS — RAG-Based ..." or
            // "Orion *(cut...)*" — the name is whatever precedes the first
            // em dash or parenthetical.
            $name = trim(preg_split('/\s+[—(]/u', $match[1])[0]);

            if ($name !== '' && stripos($resumeSections, $name) !== false) {
                $kept[] = trim("### {$match[1]}\n{$match[2]}");
            }
        }

        return $kept !== [] ? implode("\n\n", $kept) : $portfolioContent;
    }

    /**
     * Splits the trimmed resume sections (Experience + Projects + Skills)
     * into the LLM-facing part (Experience + Projects only) and the Skills
     * block, kept verbatim — see the fabrication finding in process().
     *
     * @return array{0: string, 1: string} [experienceAndProjects, skillsSection]
     */
    private function splitOffSkills(string $resumeSections): array
    {
        if (preg_match('/\n(##\s*Skills.*)\z/is', $resumeSections, $matches, PREG_OFFSET_CAPTURE)) {
            $skills = trim($matches[1][0]);
            $rest = trim(substr($resumeSections, 0, $matches[1][1]));

            return [$rest, $skills];
        }

        // No Skills header found — nothing to split off or append.
        return [$resumeSections, ''];
    }

    private function readVaultFile(string $vaultPath, string $relativePath): string
    {
        $path = rtrim($vaultPath, '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (! is_file($path)) {
            throw new RuntimeException("Eru vault file not found: {$path}");
        }

        return file_get_contents($path);
    }
}
