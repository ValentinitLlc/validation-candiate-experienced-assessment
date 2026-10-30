<?php

namespace App\Http\Requests;

use App\Models\Cause;
use Illuminate\Foundation\Http\FormRequest;

class StorePledgeRequest extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Les visiteurs anonymes peuvent soumettre des promesses de don
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cause_id' => [
                'required',
                'integer',
                'exists:causes,id',
                function ($attribute, $value, $fail) {
                    $cause = Cause::find($value);
                    if ($cause && ! $cause->is_active) {
                        $fail('Pledges cannot be accepted for an inactive Cause.');
                    }
                },
            ],
            'pledger_name' => ['required', 'string', 'min:2', 'max:255'],
            'pledger_email' => ['required', 'email', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
