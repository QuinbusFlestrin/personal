<?php

namespace App\Services\Import\Connectors;

use App\Models\Source;
use App\Services\Import\Contracts\SourceConnector;
use App\Services\Import\DTO\RawEventDTO;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

class RssConnector implements SourceConnector
{
    public function fetch(Source $source): iterable
    {
        $url = $source->config['url'] ?? null;

        if (! $url) {
            throw new RuntimeException("Source #{$source->id} ({$source->name}) is missing config.url");
        }

        $response = Http::timeout(20)->get($url);
        $response->throw();

        $xml = simplexml_load_string($response->body());

        if ($xml === false) {
            throw new RuntimeException("Source #{$source->id} ({$source->name}) returned invalid XML");
        }

        foreach ($xml->channel->item ?? [] as $item) {
            $startsAt = $this->parseDate((string) $item->pubDate);

            if ($startsAt === null) {
                continue;
            }

            yield new RawEventDTO(
                externalId: $this->extractGuid($item),
                title: trim((string) $item->title),
                description: $this->cleanDescription((string) $item->description),
                startsAt: $startsAt,
                endsAt: null,
                externalUrl: trim((string) $item->link) ?: null,
            );
        }
    }

    private function extractGuid(SimpleXMLElement $item): ?string
    {
        $guid = trim((string) $item->guid);

        return $guid !== '' ? $guid : (trim((string) $item->link) ?: null);
    }

    private function cleanDescription(string $description): ?string
    {
        $text = trim(strip_tags($description));

        return $text !== '' ? $text : null;
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
