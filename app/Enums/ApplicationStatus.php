<?php

namespace App\Enums;

/**
 * The lifecycle a single job application moves through. Kept as a PHP
 * backed enum (cast on the model, see JobApplication::casts()) rather than
 * a native DB enum column — the migration stores a plain string, so this
 * enum can gain/reorder cases without a schema change.
 */
enum ApplicationStatus: string
{
    // Actively preparing but not submitted yet — on hold / in progress
    // (e.g. drafting a cover letter). The default for a brand-new entry.
    case Saved = 'saved';

    // Submitted — now waiting on a response. Distinct from Saved: the
    // application itself has actually gone out.
    case Applied = 'applied';

    case Interviewing = 'interviewing';
    case Offer = 'offer';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
}
