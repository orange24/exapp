<?php

if (! function_exists('format_rate')) {
    /**
     * Format an exchange rate showing exactly as many decimal digits as were
     * actually keyed in / stored — never pads or forces a fixed decimal count.
     * e.g. 0.00182 -> "0.00182", 33.0400 -> "33.04", 0.220000 -> "0.22".
     */
    function format_rate(null|int|float|string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $formatted = number_format((float) $value, 6, '.', ',');

        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted;
    }
}

if (! function_exists('format_money')) {
    /**
     * Format a money value, dropping a trailing ".00" to save space on the
     * thermal print slip — a real fractional value (e.g. ".50") is kept.
     */
    function format_money(null|int|float|string $value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $formatted = number_format((float) $value, $decimals, '.', ',');

        $zeroFraction = '.' . str_repeat('0', $decimals);
        if ($decimals > 0 && str_ends_with($formatted, $zeroFraction)) {
            $formatted = substr($formatted, 0, -strlen($zeroFraction));
        }

        return $formatted;
    }
}
