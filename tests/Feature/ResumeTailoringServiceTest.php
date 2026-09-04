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
                        // ...but the actual Experience/Projects content,
                        // including inline cut-annotations, survives.
                        && str_contains($experienceAndProjects, 'Cut Project');
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
}
