<?php

namespace App\Models;

use App\Enums\AutoApplyCandidateStatus;
use App\Enums\WorkMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoApplyCandidate extends Model
{
    /** @use HasFactory<\Database\Factories\AutoApplyCandidateFactory> */
    use HasFactory;

    protected $fillable = [
        'posting_url',
        'company',
        'role',
        'salary_min',
        'salary_max',
        'hours_per_week',
        'location',
        'work_mode',
        'status',
        'posting_text',
        'match_reasoning',
        'tailored_resume_path',
        'resume_variant',
        'resume_variant_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => AutoApplyCandidateStatus::class,
            'salary_min' => 'integer',
            'salary_max' => 'integer',
            'hours_per_week' => 'integer',
            'work_mode' => WorkMode::class,
        ];
    }
}
