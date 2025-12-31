<?php

namespace App\Rules;

use App\Actions\General\EasyHashAction;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class HashIdExists implements ValidationRule
{
    protected string $table;
    protected string $column;
    protected string $type;

    public function __construct(string $table, string $column = 'id', string $type = '')
    {
        $this->table = $table;
        $this->column = $column;
        $this->type = $type;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');
            return;
        }

        $decodedId = EasyHashAction::decode($value, $this->type);

        if (! $decodedId) {
            $fail('The selected :attribute is invalid.');
            return;
        }

        $exists = DB::table($this->table)
            ->where($this->column, $decodedId)
            ->exists();

        if (! $exists) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
