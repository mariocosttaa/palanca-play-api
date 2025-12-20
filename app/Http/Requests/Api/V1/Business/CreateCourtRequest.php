<?php

namespace App\Http\Requests\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Enums\CourtTypeEnum;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request validates
 *
 * Input fields:
 * @property int|null $tenant_id
 * @property Tenant|null $tenant
 * @property int $court_type_id
 * @property string $name
 * @property int $number
 * @property array $images
 * @property \Illuminate\Http\UploadedFile|null $images.*
 * @property bool $status
 *
 * Magic/inherited methods (MANDATORY):
 * @method bool hasFile(string $key)
 * @method \Illuminate\Http\UploadedFile|null file(string $key)
 * @method mixed route(string $key = null)
 * @method bool boolean(string $key)
 * @method array all()
 * @method void merge(array $data)
 * @method array input(string $key = null, mixed $default = null)
 * @method string route(string $key = null)
 * @mixin \Illuminate\Http\Request
 */

class CreateCourtRequest extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation()
    {
        $this->merge([
            'court_type_id' => EasyHashAction::decode($this->court_type_id, 'court-type-id'),
        ]);

        if ($this->has('availabilities')) {
            $availabilities = $this->input('availabilities');
            foreach ($availabilities as &$availability) {
                if (isset($availability['is_available']) && $availability['is_available'] === false) {
                    $availability['start_time'] = $availability['start_time'] ?? '09:00';
                    $availability['end_time'] = $availability['end_time'] ?? '19:00';
                }
            }
            $this->merge(['availabilities' => $availabilities]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->tenant?->id ?? $this->input('tenant_id');

        return [
            'court_type_id' => ['required', 'integer', Rule::exists('courts_type', 'id')],
            'name' => ['required', 'string', 'min:3', 'max:255', Rule::unique('courts', 'name')->where('tenant_id', $tenantId)],
            'number' => ['required', 'numeric', 'min:1', 'max:9999', Rule::unique('courts', 'number')->where('tenant_id', $tenantId)],
            'status' => 'nullable|boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:10240',
            'availabilities' => 'nullable|array',
            'availabilities.*.day_of_week_recurring' => 'nullable|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'availabilities.*.specific_date' => 'nullable|date',
            'availabilities.*.start_time' => 'nullable|date_format:H:i',
            'availabilities.*.end_time' => 'nullable|date_format:H:i|after:availabilities.*.start_time',
            'availabilities.*.is_available' => 'boolean',
        ];
    }

    // messages and attributes are now handled by lang files
}