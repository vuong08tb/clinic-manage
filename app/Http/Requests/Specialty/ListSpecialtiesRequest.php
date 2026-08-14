<?php

namespace App\Http\Requests\Specialty;

use App\Constants\SpecialtyMessage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate filters used to paginate the specialty catalog.
 */
class ListSpecialtiesRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for specialty list filters.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom validation messages for specialty list filters.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'per_page.max' => SpecialtyMessage::PAGE_SIZE_TOO_LARGE,
        ];
    }
}
