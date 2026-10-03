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

if (! function_exists('rate_input_value')) {
    /**
     * Format a rate for the `value` of an <input type="number">.
     *
     * Casting a float straight to string is not safe here: PHP switches to
     * scientific notation below 1e-5, so an IDR adjustment of -0.00001 reaches
     * the form as "-1.0E-5". The browser accepts that, but a counter clerk
     * cannot read it, and it is the currencies with the smallest rates — the
     * ones that need six decimals in the first place — that fall below the line.
     *
     * Unlike format_rate() this emits no thousands separator, because a comma
     * makes the value invalid for a number input.
     */
    function rate_input_value(null|int|float|string $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        $formatted = number_format((float) $value, 6, '.', '');

        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
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
