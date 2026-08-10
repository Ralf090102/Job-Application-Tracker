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
    case Saved = 'saved';
    case Applied = 'applied';
    case Interviewing = 'interviewing';
    case Offer = 'offer';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
}
