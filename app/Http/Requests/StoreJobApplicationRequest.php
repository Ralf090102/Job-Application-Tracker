<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use App\Enums\WorkMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreJobApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * No auth yet (that's Phase 6) — everyone is authorized for now.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'max:255'],
            'status' => ['required', new Enum(ApplicationStatus::class)],
            'salary_min' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'salary_max' => ['nullable', 'integer', 'min:0', 'max:1000000', 'gte:salary_min'],
            'posting_url' => ['nullable', 'url', 'max:2048'],
            'posting_text' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'work_mode' => ['nullable', new Enum(WorkMode::class)],
            'red_flags' => ['nullable', 'array'],
            'red_flags.*' => ['string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
