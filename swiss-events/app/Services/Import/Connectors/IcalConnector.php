<?php

namespace App\Services\Import\Connectors;

use App\Models\Source;
use App\Services\Import\Contracts\SourceConnector;
use App\Services\Import\DTO\RawEventDTO;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal hand-rolled VEVENT parser (RFC 5545 subset) — deliberately avoids
 * an extra composer dependency for a format simple enough to line-parse.
 */
class IcalConnector implements SourceConnector
{
    public function fetch(Source $source): iterable
    {
        $url = $source->config['url'] ?? null;

        if (! $url) {
            throw new RuntimeException("Source #{$source->id} ({$source->name}) is missing config.url");
        }

        $response = Http::timeout(20)->get($url);
        $response->throw();

        foreach ($this->splitEvents($this->unfold($response->body())) as $block) {
            $fields = $this->parseFields($block);
            $startsAt = $this->parseDate($fields['DTSTART'] ?? null);

            if (! isset($fields['SUMMARY']) || $startsAt === null) {
                continue;
            }

            yield new RawEventDTO(
                externalId: $fields['UID'] ?? null,
                title: $this->unescape($fields['SUMMARY']),
                description: isset($fields['DESCRIPTION']) ? $this->unescape($fields['DESCRIPTION']) : null,
                startsAt: $startsAt,
                endsAt: $this->parseDate($fields['DTEND'] ?? null),
                venueName: isset($fields['LOCATION']) ? $this->unescape($fields['LOCATION']) : null,
                externalUrl: $fields['URL'] ?? null,
            );
        }
    }

    /**
     * Un-fold RFC 5545 line continuations (a leading space/tab means "append to previous line").
     */
    private function unfold(string $ics): string
    {
        return preg_replace("/\r?\n[ \t]/", '', $ics) ?? $ics;
    }

    /**
     * @return array<int, string>
     */
    private function splitEvents(string $ics): array
    {
        preg_match_all('/BEGIN:VEVENT\r?\n(.*?)END:VEVENT/s', $ics, $matches);

        return $matches[1] ?? [];
    }

    /**
     * @return array<string, string>
     */
    private function parseFields(string $block): array
    {
        $fields = [];

        foreach (preg_split('/\r?\n/', trim($block)) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $name = strtoupper(explode(';', $key)[0]);
            $fields[$name] = $value;
        }

        return $fields;
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{8}$/', $value)) {
                return CarbonImmutable::createFromFormat('Ymd', $value)?->startOfDay();
            }

            $clean = rtrim($value, 'Z');
            $date = CarbonImmutable::createFromFormat('Ymd\THis', $clean);

            return str_ends_with($value, 'Z') ? $date?->utc() : $date;
        } catch (\Throwable) {
            return null;
        }
    }

    private function unescape(string $value): string
    {
        return str_replace(['\\,', '\\;', '\\n', '\\N', '\\\\'], [',', ';', "\n", "\n", '\\'], $value);
    }
}
