<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientInfoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Since authentication is removed, allow all requests for now
        // In a real scenario, you might want to implement API key authentication or other authorization
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $compteId = $this->route('compte');

        return [
            'titulaire' => 'sometimes|string|max:255',
            'informationsClient' => 'sometimes|array',
            'informationsClient.telephone' => [
                'sometimes',
                'string',
                'regex:/^\+221[76-8][0-9]{7}$/',
                Rule::unique('clients', 'telephone')->ignore($compteId->client_id, 'id'),
            ],
            'informationsClient.email' => [
                'sometimes',
                'email',
                Rule::unique('clients', 'email')->ignore($compteId->client_id, 'id'),
            ],
            'informationsClient.password' => 'sometimes|string|min:8',
            'informationsClient.nci' => 'sometimes|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'informationsClient.telephone.regex' => 'Le téléphone doit être au format sénégalais (+221XXXXXXXXX).',
            'informationsClient.telephone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'informationsClient.email.unique' => 'Cet email est déjà utilisé.',
            'informationsClient.password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->all();

            // Check if at least one field is provided
            $hasTitulaire = isset($data['titulaire']);
            $hasClientInfo = isset($data['informationsClient']) && !empty(array_filter($data['informationsClient']));

            if (!$hasTitulaire && !$hasClientInfo) {
                $validator->errors()->add('general', 'Au moins un champ de modification doit être fourni.');
            }
        });
    }
}