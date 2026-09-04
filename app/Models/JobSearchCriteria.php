<?php

namespace App\Models;

use App\Enums\WorkMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobSearchCriteria extends Model
{
    /** @use HasFactory<\Database\Factories\JobSearchCriteriaFactory> */
    use HasFactory;

    // Eloquent's default table-name guess pluralizes the class name's last
    // word ("Criteria" -> "Criterias" — the inflector doesn't know Criteria
    // is already the plural of Criterion), producing job_search_criterias.
    // That's wrong; the migration creates job_search_criteria. Override.
    protected $table = 'job_search_criteria';

    protected $fillable = [
        'position_keywords',
        'hours_min',
        'hours_max',
        'salary_min',
        'salary_max',
        'work_mode',
        'location',
        'avoid_if_rubric',
    ];

    protected function casts(): array
    {
        return [
            'position_keywords' => 'array', // JSON column <-> PHP array, automatic
            'hours_min' => 'integer',
            'hours_max' => 'integer',
            'salary_min' => 'integer',
            'salary_max' => 'integer',
            'work_mode' => WorkMode::class,
        ];
    }
}
