<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutoApplyCandidateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'posting_url' => $this->posting_url,
            'company' => $this->company,
            'role' => $this->role,
            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,
            'hours_per_week' => $this->hours_per_week,
            'location' => $this->location,
            'work_mode' => $this->work_mode?->value,
            'status' => $this->status->value,
            'posting_text' => $this->posting_text,
            'match_reasoning' => $this->match_reasoning,
            'resume_variant' => $this->resume_variant,
            'resume_variant_reason' => $this->resume_variant_reason,
            'tailored_resume_path' => $this->tailored_resume_path,
            // Convenience for the jat-review-queue skill: the tailored
            // Markdown sits next to the PDF as resume.md
            // (ResumeTailoringService writes both under
            // auto-apply/{id}/) — computed here so the skill doesn't have
            // to hardcode that sibling-file convention itself.
            'tailored_resume_markdown_path' => $this->tailored_resume_path
                ? preg_replace('/\.pdf$/', '.md', $this->tailored_resume_path)
                : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
