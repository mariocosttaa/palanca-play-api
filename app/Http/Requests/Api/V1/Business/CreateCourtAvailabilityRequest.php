<?php

namespace App\Http\Requests\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCourtAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'court_id' => 'nullable|string',
            'court_type_id' => 'nullable|string',
            'day_of_week_recurring' => 'nullable|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'specific_date' => 'nullable|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'is_available' => 'boolean',
        ];
    }

    public function prepareForValidation()
    {
        if ($this->has('court_id')) {
            $this->merge([
                'court_id' => EasyHashAction::decode($this->court_id, 'court-id'),
            ]);
        }

        if ($this->has('court_type_id')) {
            $this->merge([
                'court_type_id' => EasyHashAction::decode($this->court_type_id, 'court-type-id'),
            ]);
        }
    }
}
