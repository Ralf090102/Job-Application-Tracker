<?php

namespace App\Http\Requests;

use App\Enums\AutoApplyCandidateStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class IndexAutoApplyCandidatesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Real access control here is the shared-secret token middleware
     * (VerifyAutoApplyIngestToken) in front of this route, not Sanctum —
     * this is a local Claude Code session calling its own backend, not the
     * browser SPA.
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
            'status' => ['nullable', new Enum(AutoApplyCandidateStatus::class)],
        ];
    }
}
