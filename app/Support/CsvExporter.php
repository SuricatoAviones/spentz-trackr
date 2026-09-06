<?php

namespace App\Support;

final class CsvExporter
{
    /**
     * Guard a user-controlled cell against CSV spreadsheet formula injection.
     * Prefix cells that begin with an injection trigger character (=, +, -, @, tab, CR).
     */
    public static function cell(?string $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return $value;
        }

        foreach (['=', '+', '-', '@', "\t", "\r"] as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return "'".$value;
            }
        }

        return $value;
    }
}
