<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\SafeRedirectUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreShortUrlRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'original_url' => ['bail', 'required', 'string', 'max:2048', 'url:http,https', new SafeRedirectUrl],
        ];
    }

    /**
     * 検証済みの元のURL（検証済みの値だけを使い、他の項目は無視する）。
     */
    public function originalUrl(): string
    {
        return $this->safe()->string('original_url')->toString();
    }
}
