<?php

namespace Tests\Feature;

use App\Contracts\PdfRenderer;
use App\Contracts\ResumeTailor;
use App\Enums\AutoApplyCandidateStatus;
use App\Exceptions\PdfRenderException;
use App\Exceptions\ResumeTailoringException;
use App\Models\AutoApplyCandidate;
use App\Services\ResumeTailoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResumeTailoringServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Isolated fake disk per test — without this, files written by one
        // test (and their real absolute paths) leak into the next, since
        // RefreshDatabase resets the DB but not the filesystem.
        Storage::fake('local');
    }

    private function useFixtureVault(): void
    {
        config(['services.eru.vault_path' => base_path('tests/Fixtures/eru-vault')]);
    }

    private function candidate(array $overrides = []): AutoApplyCandidate
    {
        return AutoApplyCandidate::factory()->create(array_merge([
            'company' => 'Acme Corp',
            'role' => 'Backend Developer',
            'posting_text' => 'Backend Developer role, PHP/Laravel.',
            'status' => AutoApplyCandidateStatus::Matched,
            // The factory's default is faker->optional()->filePath(), which
            // occasionally generates a real random path — pin to null so
            // "tailored_resume_path stayed unset" assertions aren't flaky.
            'tailored_resume_path' => null,
        ], $overrides));
    }

    public function test_does_nothing_when_vault_path_is_not_configured(): void
    {
        config(['services.eru.vault_path' => null]);

        $this->mock(ResumeTailor::class, function ($mock) {
            $mock->shouldNotReceive('selectVariant');
            $mock->shouldNotReceive('tailor');
        });

        $candidate = $this->candidate();

        app(ResumeTailoringService::class)->process($candidate);

        $this->assertSame(AutoApplyCandidateStatus::Matched, $candidate->fresh()->status);
    }

    public function test_selects_a_variant_tailors_and_renders_to_ready_for_review(): void
    {
        $this->useFixtureVault();

        $this->mock(ResumeTailor::class, function ($mock) {
            $mock->shouldReceive('selectVariant')
                ->once()
                ->withArgs(function ($role, $company, $postingText, $summaries) {
                    return $role === 'Backend Developer'
                        && $company === 'Acme Corp'
                        && array_keys($summaries) === ['SD', 'PE', 'PE2']
                        // Pulled from the fixture note's own "## Goal" section.
                        && str_contains($summaries['SD'], 'Software Development / Software Engineer roles');
                })
                ->andReturn(['variant' => 'SD', 'reason' => 'Best general full-stack fit.']);

            $mock->shouldReceive('tailor')
                ->once()
                ->withArgs(function ($role, $company, $postingText, $experienceAndProjects, $portfolioMarkdown) {
                    return str_contains($portfolioMarkdown, 'test@example.com')
                        // Status/Goal meta-commentary trimmed off the top...
                        && ! str_contains($experienceAndProjects, 'Resume — Software Development')
                        && ! str_contains($experienceAndProjects, 'fixture note')
                        // ...Open questions/TODO trimmed off the bottom...
                        && ! str_contains($experienceAndProjects, 'Fixture-only placeholder')
                        // ...Skills trimmed off entirely (spliced in
                        // verbatim afterward instead — see splitOffSkills)...
                        && ! str_contains($experienceAndProjects, 'Full-Stack Development')
                        // ...and anything marked reference-only is stripped
                        // deterministically before the LLM ever sees it — see
                        // stripReferenceOnlyBlocks(). Previously this content
                        // survived extraction and relied on the model to
                        // exclude it per the prompt's own instructions; a
                        // real live run (2026-09-10 smoke test) showed a
                        // local model doesn't reliably honor that.
                        && ! str_contains($experienceAndProjects, 'Cut Project')
                        // ...but the actual current content survives.
                        && str_contains($experienceAndProjects, 'WiQAS');
                })
                ->andReturn("# Test Candidate\ntest@example.com\n\n## Experience\n- Built things.\n\n## Projects\n- Shipped things.");
        });

        $this->mock(PdfRenderer::class, function ($mock) {
            $mock->shouldReceive('render')->once();
        });

        $candidate = $this->candidate();

        app(ResumeTailoringService::class)->process($candidate);

        $fresh = $candidate->fresh();
        $this->assertSame(AutoApplyCandidateStatus::ReadyForReview, $fresh->status);
        $this->assertSame('SD', $fresh->resume_variant);
        $this->assertSame('Best general full-stack fit.', $fresh->resume_variant_reason);
        $this->assertNotNull($fresh->tailored_resume_path);

        $saved = Storage::disk('local')->get("auto-apply/{$candidate->id}/resume.md");
        Storage::disk('local')->assertExists("auto-apply/{$candidate->id}/resume.md");
        $this->assertStringContainsString('## Experience', $saved);
        // The Skills section wasn't part of the mocked tailor() return —
        // it must have come from the verbatim splice, straight from the
        // fixture's own Skills line.
        $this->assertStringContainsString('## Skills', $saved);
        $this->assertStringContainsString('PHP (Laravel), React, Python', $saved);
    }

    public function test_leaves_candidate_at_matched_when_the_llm_call_fails(): void
    {
        $this->useFixtureVault();

        $this->mock(ResumeTailor::class, function ($mock) {
            $mock->shouldReceive('selectVariant')->once()->andThrow(
                new ResumeTailoringException("Couldn't reach Ollama.")
            );
        });

        $this->mock(PdfRenderer::class, function ($mock) {
            $mock->shouldNotReceive('render');
        });

        $candidate = $this->candidate();

        app(ResumeTailoringService::class)->process($candidate);

        $fresh = $candidate->fresh();
        $this->assertSame(AutoApplyCandidateStatus::Matched, $fresh->status);
        $this->assertNull($fresh->tailored_resume_path);
        Storage::disk('local')->assertMissing("auto-apply/{$candidate->id}/resume.md");
    }

    public function test_leaves_candidate_at_tweaked_when_pdf_render_fails(): void
    {
        $this->useFixtureVault();

        $this->mock(ResumeTailor::class, function ($mock) {
            $mock->shouldReceive('selectVariant')->once()->andReturn(['variant' => 'PE', 'reason' => 'Fits.']);
            $mock->shouldReceive('tailor')->once()->andReturn('# Resume content');
        });

        $this->mock(PdfRenderer::class, function ($mock) {
            $mock->shouldReceive('render')->once()->andThrow(
                new PdfRenderException('Proteus is not installed.')
            );
        });

        $candidate = $this->candidate();

        app(ResumeTailoringService::class)->process($candidate);

        $fresh = $candidate->fresh();
        $this->assertSame(AutoApplyCandidateStatus::Tweaked, $fresh->status);
        $this->assertSame('PE', $fresh->resume_variant);
        $this->assertNull($fresh->tailored_resume_path);
    }

    public function test_logs_a_warning_when_resume_heading_structure_is_not_found(): void
    {
        // Regression: this fallback used to be silent, so the safety trim
        // (private Status/TODO notes stripped, prompt kept under the
        // measured timeout threshold) could quietly stop working with no
        // signal that it had (/bug-sweep 2026-09-04).
        Log::shouldReceive('warning')
            ->once()
            ->with(\Mockery::pattern('/expected heading structure not found/'));

        $service = app(ResumeTailoringService::class);
        $method = new \ReflectionMethod($service, 'extractCoreResumeSections');
        $method->setAccessible(true);

        $malformed = "# Just a title\n\nNo matching headings here at all.";
        $result = $method->invoke($service, $malformed);

        // Still falls back to sending the whole note, just no longer silently.
        $this->assertSame($malformed, $result);
    }

    public function test_strips_reference_only_blocks_regardless_of_heading_level(): void
    {
        // Regression for the 2026-09-10 smoke-test finding: a real tailored
        // resume (candidate #33) shipped two nonprofit-site entries twice
        // (once correctly merged, once again from a "(reference — merged
        // above)" sub-entry) because extraction sent both to the LLM and
        // trusted it to exclude the second per the prompt's own
        // instructions — it didn't. Covers a "###"-level heading too, not
        // just "####", since the real bug involved both levels.
        $note = <<<'MD'
            ### Experience

            #### Kept Entry
            - This one has no reference marker and must survive.

            #### Old Entry (Private Repo) *(reference — merged above)*
            - This one must never reach the LLM.

            ### Projects

            ### Also Cut *(kept here for reference/future re-tailoring)*
            - A "###"-level reference block must be stripped too.

            #### Still Kept
            - This one comes after a stripped block and must survive.

            ## Open questions / TODO
            - [ ] Must never leak either.
            MD;

        $service = app(ResumeTailoringService::class);
        $method = new \ReflectionMethod($service, 'extractCoreResumeSections');
        $method->setAccessible(true);

        $result = $method->invoke($service, $note);

        $this->assertStringContainsString('Kept Entry', $result);
        $this->assertStringContainsString('Still Kept', $result);
        $this->assertStringNotContainsString('Old Entry', $result);
        $this->assertStringNotContainsString('Also Cut', $result);
        $this->assertStringNotContainsString('Open questions', $result);
    }

    public function test_sanitizes_a_fenced_response_with_a_fabricated_trailing_skills_section(): void
    {
        // Regression for the 2026-09-10 smoke-test finding: a real
        // re-tailoring run (candidate #33) wrapped its output in a code
        // fence, then appended its own fabricated "## Skills" section
        // after the closing fence with skills lifted from the posting's
        // own wording rather than the candidate's real ones. Both must be
        // dropped — the real Skills section is spliced in separately by
        // the caller, verbatim from the source, never from the model.
        $llmOutput = <<<'MD'
            ```
            # Test Candidate
            test@example.com

            ## Experience
            - Built things.

            ## Projects
            - Shipped things.
            ```
            The skills section is:

            ## Skills
            - React Native
            - Generative AI tools
            MD;

        $service = app(ResumeTailoringService::class);
        $method = new \ReflectionMethod($service, 'sanitizeTailoredOutput');
        $method->setAccessible(true);

        $result = $method->invoke($service, $llmOutput);

        $this->assertStringContainsString('## Experience', $result);
        $this->assertStringContainsString('## Projects', $result);
        $this->assertStringNotContainsString('```', $result);
        $this->assertStringNotContainsString('React Native', $result);
        $this->assertStringNotContainsString('Generative AI tools', $result);
        $this->assertStringNotContainsString('The skills section is', $result);
    }

    public function test_sanitize_is_a_no_op_on_a_well_formed_response(): void
    {
        $service = app(ResumeTailoringService::class);
        $method = new \ReflectionMethod($service, 'sanitizeTailoredOutput');
        $method->setAccessible(true);

        $wellFormed = "# Test Candidate\ntest@example.com\n\n## Experience\n- Built things.\n\n## Projects\n- Shipped things.";

        $this->assertSame($wellFormed, $method->invoke($service, $wellFormed));
    }
}
