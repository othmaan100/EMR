<?php

namespace App\Imports;

/**
 * One column of an import template.
 */
final class ImportColumn
{
    /**
     * @param  list<mixed>  $rules  Laravel validation rules (besides required/nullable)
     * @param  list<string>|null  $allowed  accepted values, listed in the instructions
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly bool $required = false,
        public readonly array $rules = [],
        public readonly ?string $example = null,
        public readonly ?string $help = null,
        public readonly ?array $allowed = null,
        public readonly ?string $example2 = null,
    ) {}

    /**
     * @return list<mixed>
     */
    public function validationRules(): array
    {
        return [$this->required ? 'required' : 'nullable', ...$this->rules];
    }
}
