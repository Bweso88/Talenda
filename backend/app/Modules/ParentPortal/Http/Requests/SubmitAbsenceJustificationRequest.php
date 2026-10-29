<?php

declare(strict_types=1);

namespace App\Modules\ParentPortal\Http\Requests;

use App\Modules\SchoolLife\Enums\JustificationReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SubmitAbsenceJustificationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'reason' => ['required', Rule::enum(JustificationReason::class)],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ends_on.after_or_equal' => 'La date de fin doit être le même jour que la date de début ou après.',
        ];
    }
}
