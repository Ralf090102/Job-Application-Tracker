<?php

namespace App\Enums;

/**
 * The lifecycle a discovered posting moves through before (and instead of,
 * if it never gets approved) becoming a real JobApplication row. Plain
 * string column + PHP backed enum cast, same pattern as ApplicationStatus —
 * see the migration creating auto_apply_candidates and
 * JAT-Roadmap-AutoApply.md Phase 1 for why not a native DB enum.
 */
enum AutoApplyCandidateStatus: string
{
    // Freshly ingested from JSearch, not yet matched against criteria.
    case Discovered = 'discovered';

    // Passed deterministic + LLM rubric matching (Phase 2).
    case Matched = 'matched';

    // Resume tailoring has run for this candidate (Phase 3).
    case Tweaked = 'tweaked';

    // Tailored resume rendered to PDF; waiting on human review via the
    // Claude Code review-queue skill (Phase 5).
    case ReadyForReview = 'ready_for_review';

    // Rejected during human review.
    case Rejected = 'rejected';

    // Approved and submitted for real — the moment a matching JobApplication
    // row gets created in v1's schema.
    case Submitted = 'submitted';

    // Posting requires an external ATS (Workday/Greenhouse/Lever/etc.) —
    // out of scope for automated submit, logged and left for manual apply.
    case SkippedExternalAts = 'skipped_external_ats';

    // Ingest-time dedup: posting_url already existed.
    case SkippedDuplicate = 'skipped_duplicate';
}
