<?php

namespace App\Enums;

/**
 * Same pattern as ApplicationStatus: plain string column + PHP backed enum
 * cast, not a native DB enum — see the migration adding this column and
 * Roadmap.md Phase 2 for why.
 */
enum WorkMode: string
{
    case Onsite = 'onsite';
    case Remote = 'remote';
    case Hybrid = 'hybrid';
}
