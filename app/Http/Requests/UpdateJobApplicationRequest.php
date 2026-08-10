<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateJobApplicationRequest extends FormRequest
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
     * `sometimes` on every field (rather than `required`, as in the store
     * request) so a partial PATCH — changing just one field — doesn't
     * need to resend the whole record.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', new Enum(ApplicationStatus::class)],
            'salary_min' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'salary_max' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000', 'gte:salary_min'],
            'posting_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'posting_text' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
