<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobApplicationResource extends JsonResource
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
            'company' => $this->company,
            'role' => $this->role,
            // ->value, explicitly, rather than relying on PHP's native
            // BackedEnum JSON serialization — easier to see what's
            // happening at a glance than the implicit behavior.
            'status' => $this->status->value,
            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,
            'posting_url' => $this->posting_url,
            'posting_text' => $this->posting_text,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
