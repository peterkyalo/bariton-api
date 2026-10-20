<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'privacy_consent' => ['accepted'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
        ];

        // Conditional guardian requirement for minors (under 18)
        if ($this->filled('date_of_birth')) {
            try {
                $dob = Carbon::parse($this->input('date_of_birth'));
                if ($dob->diffInYears(now()) < 18) {
                    $rules['guardian_name'] = ['required', 'string', 'max:191'];
                    $rules['guardian_email'] = ['required', 'string', 'email', 'max:191', 'different:email'];
                }
            } catch (\Exception) {
                // Handled by date rule
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'privacy_consent.accepted' => 'You must review and agree to the Data Privacy Policy under the Kenya Data Protection Act 2019 to proceed.',
            'guardian_name.required' => 'As you are under 18 years of age, parent or legal guardian details are required.',
            'guardian_email.required' => 'Parent or legal guardian email address is required for minors.',
            'guardian_email.different' => 'Guardian email cannot be the same as the student email.',
        ];
    }
}
