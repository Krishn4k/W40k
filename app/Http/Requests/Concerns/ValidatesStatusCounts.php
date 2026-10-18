<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PaintStatus;
use Illuminate\Validation\Validator;

/**
 * Validation of a lot's breakdown by paint status ("statuses[on_sprue]" …): whole numbers, 1 to MAX_QUANTITY models in all.
 */
trait ValidatesStatusCounts
{
    public const MAX_QUANTITY = 999;

    /**
     * @return array<string, array<int, string>>
     */
    protected function statusCountRules(): array
    {
        $rules = ['statuses' => ['required', 'array']];

        foreach (PaintStatus::cases() as $status) {
            $rules["statuses.{$status->value}"] = ['nullable', 'integer', 'min:0', 'max:'.self::MAX_QUANTITY];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function statusCountAttributes(): array
    {
        return collect(PaintStatus::cases())
            ->mapWithKeys(fn (PaintStatus $status): array => ["statuses.{$status->value}" => mb_strtolower($status->label())])
            ->all();
    }

    protected function validateTotalQuantity(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $quantity = array_sum($this->statusCounts());

        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            $validator->errors()->add('statuses', 'Un lot doit contenir entre 1 et '.self::MAX_QUANTITY.' figurines.');
        }
    }

    /**
     * Number of models per status value, statuses without models left out.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        return collect(PaintStatus::cases())
            ->mapWithKeys(fn (PaintStatus $status): array => [$status->value => (int) $this->input("statuses.{$status->value}")])
            ->filter()
            ->all();
    }
}
