<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
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
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'salary_min' => 'integer',
            'salary_max' => 'integer',
        ];
    }
}
