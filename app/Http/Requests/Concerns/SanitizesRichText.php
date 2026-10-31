<?php

namespace App\Http\Requests\Concerns;

use App\Services\HtmlSanitizer;

trait SanitizesRichText
{
    /**
     * Strip dangerous markup (scripts, event handlers, javascript: urls ...) from rich text inputs
     * before they are validated and persisted.
     *
     * @param  array<int, string>  $fields
     */
    protected function sanitizeRichTextFields(array $fields): void
    {
        $sanitizer = app(HtmlSanitizer::class);
        $clean = [];

        foreach ($fields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $clean[$field] = $sanitizer->clean($this->input($field));
            }
        }

        if ($clean) {
            $this->merge($clean);
        }
    }
}
