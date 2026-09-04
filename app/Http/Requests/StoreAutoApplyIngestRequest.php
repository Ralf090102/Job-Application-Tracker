<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAutoApplyIngestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Real access control here is the shared-secret token middleware
     * (VerifyAutoApplyIngestToken) in front of this route, not Sanctum —
     * this is a machine-to-machine endpoint n8n calls, not the browser SPA.
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
            'postings' => ['required', 'array'],
            'postings.*' => ['array'],
        ];
    }
}
