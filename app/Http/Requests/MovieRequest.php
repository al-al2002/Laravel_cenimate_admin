<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MovieRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'genre' => 'required|string|max:100',
            'language' => 'required|string|max:100',
            'duration_minutes' => 'required|integer|min:1',
            'rating' => 'nullable|string|max:10',
            'trailer_url' => 'nullable|url',
            'cast' => 'nullable|string',
            'release_date' => 'required|date',
            'is_active' => 'sometimes|boolean',
            'country' => 'nullable|string',
            'duration' => 'nullable|integer|min:1',
        ];

        if ($this->isMethod('post')) {
            $rules['poster'] = 'nullable|image|max:5120';
            $rules['poster_url'] = 'nullable|url';
        } elseif ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules['poster'] = 'nullable|image|max:5120';
            $rules['poster_url'] = 'nullable|url';
        }

        return $rules;
    }
}
