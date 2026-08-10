<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExtractJobPostingRequest extends FormRequest
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
            // min:20 filters out accidental empty/near-empty pastes before
            // they reach the model; max is a sanity ceiling, not a real limit.
            'posting_text' => ['required', 'string', 'min:20', 'max:20000'],
        ];
    }
}
