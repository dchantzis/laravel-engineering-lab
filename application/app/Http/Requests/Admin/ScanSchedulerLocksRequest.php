<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ScanSchedulerLocksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'release' => 'nullable|boolean',
            'show_all' => 'nullable|boolean',
            'threshold' => 'nullable|integer|min:1',
        ];
    }
}
