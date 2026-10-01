<?php

namespace App\Integrations\Lab;

use RuntimeException;

/**
 * Minimal HL7 v2 reader for result messages (ORU^R01) from analysers or
 * lab middleware, and the matching ACK. Accepts optional MLLP framing.
 */
final class Hl7Message
{
    /**
     * @return array{control_id: ?string, sending_app: ?string, samples: array<string, list<array{code: string, name: ?string, value: string, unit: ?string}>>}
     */
    public static function parse(string $raw): array
    {
        $raw = trim(str_replace(["\x0b", "\x1c"], '', $raw));
        $segments = array_values(array_filter(preg_split('/\r\n|\r|\n/', $raw), fn ($s) => trim($s) !== ''));

        if (! $segments || ! str_starts_with($segments[0], 'MSH')) {
            throw new RuntimeException('Not an HL7 v2 message (must start with MSH).');
        }

        $sep = substr($segments[0], 3, 1) ?: '|';
        $comp = substr($segments[0], 4, 1) ?: '^';
        $msh = explode($sep, $segments[0]);
        // For MSH the separator itself is field 1, so MSH-n is index n-1.
        $controlId = $msh[9] ?? null;
        $sendingApp = isset($msh[2]) ? explode($comp, $msh[2])[0] : null;

        $samples = [];
        $current = null;
        $orderIds = [];

        foreach ($segments as $segment) {
            $f = explode($sep, $segment);
            $first = fn (int $i) => isset($f[$i]) ? trim(explode($comp, $f[$i])[0]) : '';

            switch ($f[0]) {
                case 'ORC':
                    $orderIds = array_filter([$first(3), $first(2)]);
                    break;
                case 'OBR':
                    // Sample / order id: filler (OBR-3), placer (OBR-2), then ORC.
                    $current = collect([$first(3), $first(2), ...$orderIds])->first(fn ($v) => $v !== '');
                    break;
                case 'SPM':
                    $current = $first(2) ?: $current;
                    break;
                case 'OBX':
                    $status = strtoupper($first(11));
                    if (in_array($status, ['X', 'D', 'W'], true)) {
                        break; // cancelled / deleted / wrong: ignore
                    }
                    $components = explode($comp, $f[3] ?? '');
                    $code = trim($components[0] ?? '');
                    $value = trim(str_replace($comp, ' ', $f[5] ?? ''));
                    if ($current === null || $code === '' || $value === '') {
                        break;
                    }
                    $samples[$current][] = [
                        'code' => $code,
                        'name' => isset($components[1]) ? trim($components[1]) : null,
                        'value' => $value,
                        'unit' => $first(6) ?: null,
                    ];
                    break;
            }
        }

        return ['control_id' => $controlId, 'sending_app' => $sendingApp, 'samples' => $samples];
    }

    /**
     * ACK with AA (accepted) or AE (error).
     */
    public static function ack(?string $controlId, bool $ok, string $text, ?string $receivingApp = null): string
    {
        $text = str_replace(['|', '^', '~', '\\', '&', "\r", "\n"], ' ', $text);
        $now = now()->format('YmdHis');

        return "MSH|^~\\&|EMR|".config('app.name')."|".($receivingApp ?? '')."||{$now}||ACK^R01|ACK{$now}|P|2.5\r"
            .'MSA|'.($ok ? 'AA' : 'AE')."|".($controlId ?? '')."|{$text}\r";
    }
}
