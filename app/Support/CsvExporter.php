<?php

namespace App\Support;

use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Stream a UTF-8 CSV download (with BOM so Excel reads accents correctly).
     *
     * The $writeRows callback receives a `writeRow(list<scalar|null>): void` closure
     * and is responsible for iterating the data (typically via `->chunk()`).
     *
     * @param  list<string>  $columns
     * @param  Closure(Closure(array<int, scalar|null>): void): void  $writeRows
     * @param  array<string, string>  $headers
     */
    public static function download(string $filename, array $columns, Closure $writeRows, array $headers = []): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $writeRows): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns);

            $writeRows(static function (array $row) use ($out): void {
                fputcsv($out, $row);
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            ...$headers,
        ]);
    }
}
