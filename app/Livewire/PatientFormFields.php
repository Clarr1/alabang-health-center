<?php

namespace App\Livewire;

trait PatientFormFields
{
    public $first_name = '';

    public $middle_name = '';

    public $last_name = '';

    public $birthdate = '';

    public $age = '';

    public $sex = 'Male';

    public $address = '';

    public $contact_number = '';

    public $emergency_contact = '';

    public $registration_date = '';

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birthdate' => ['required', 'date'],
            'age' => ['required', 'integer', 'min:0', 'max:150'],
            'sex' => ['required', 'in:Male,Female'],
            'address' => ['required', 'string'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'registration_date' => ['required', 'date'],
        ];
    }
}
