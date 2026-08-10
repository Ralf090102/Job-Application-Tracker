<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\WorkMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    /** @use HasFactory<\Database\Factories\JobApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'company',
        'role',
        'status',
        'salary_min',
        'salary_max',
        'posting_url',
        'posting_text',
        'location',
        'work_mode',
        'red_flags',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'salary_min' => 'integer',
            'salary_max' => 'integer',
            'work_mode' => WorkMode::class,
            'red_flags' => 'array', // JSON column <-> PHP array, automatic
        ];
    }
}
