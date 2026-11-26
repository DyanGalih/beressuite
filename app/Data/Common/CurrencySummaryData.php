<?php

namespace App\Data\Common;

use App\Data\Data;
use App\Models\Currency;

class CurrencySummaryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $code,
        public string $symbol,
        public int $precision,
        public string $thousand_separator,
        public string $decimal_separator,
        public bool $swap_currency_symbol,
        public float|int|string|null $exchange_rate = null,
    ) {
    }

    public static function fromModel(?Currency $currency): ?self
    {
        if (! $currency) {
            return null;
        }

        return new self(
            id: (int) $currency->id,
            name: (string) $currency->name,
            code: (string) $currency->code,
            symbol: (string) $currency->symbol,
            precision: (int) ($currency->precision ?? 2),
            thousand_separator: (string) ($currency->thousand_separator ?? ','),
            decimal_separator: (string) ($currency->decimal_separator ?? '.'),
            swap_currency_symbol: (bool) ($currency->swap_currency_symbol ?? false),
            exchange_rate: $currency->exchange_rate,
        );
    }
}
