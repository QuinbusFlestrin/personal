<?php

/**
 * Regenerates resources/geo/swiss-cantons.json.
 *
 * Imported sources (Switzerland Tourism's attractions, for one) frequently give
 * coordinates but no address, so the canton — the site's primary filter — has to
 * be derived from the point itself. Doing that locally with bundled boundaries
 * avoids a per-item call to a geocoding service, which would be slow, rate
 * limited and a runtime dependency for a nightly job on shared hosting.
 *
 * Source: https://github.com/hyperknot/country-levels-export (OSM-derived,
 * ODbL — see the licence note written into the output file).
 *
 * Run manually when boundaries need refreshing; not part of the build:
 *   php database/geo/build-canton-boundaries.php
 */
$codes = [
    'AG', 'AI', 'AR', 'BE', 'BL', 'BS', 'FR', 'GE', 'GL', 'GR', 'JU', 'LU', 'NE',
    'NW', 'OW', 'SG', 'SH', 'SO', 'SZ', 'TG', 'TI', 'UR', 'VD', 'VS', 'ZG', 'ZH',
];

$base = 'https://raw.githubusercontent.com/hyperknot/country-levels-export/master/geojson/high/iso2/CH';
$out = [];

foreach ($codes as $code) {
    $raw = @file_get_contents("{$base}/CH-{$code}.geojson");

    if ($raw === false) {
        fwrite(STDERR, "FAILED to download {$code}\n");
        exit(1);
    }

    $feature = json_decode($raw, true);
    $geometry = $feature['geometry'] ?? null;

    if (! $geometry) {
        fwrite(STDERR, "No geometry for {$code}\n");
        exit(1);
    }

    // Normalise Polygon and MultiPolygon into one shape: a list of polygons,
    // each a list of rings, so the locator has a single case to handle.
    $polygons = $geometry['type'] === 'MultiPolygon'
        ? $geometry['coordinates']
        : [$geometry['coordinates']];

    $minLon = $minLat = INF;
    $maxLon = $maxLat = -INF;

    foreach ($polygons as $rings) {
        foreach ($rings[0] as [$lon, $lat]) {
            $minLon = min($minLon, $lon);
            $maxLon = max($maxLon, $lon);
            $minLat = min($minLat, $lat);
            $maxLat = max($maxLat, $lat);
        }
    }

    // Coordinates are rounded to ~1m; full float precision would double the
    // file size for accuracy no canton lookup can use.
    $round = function (array $rings) {
        return array_map(
            fn (array $ring) => array_map(
                fn (array $point) => [round($point[0], 5), round($point[1], 5)],
                $ring
            ),
            $rings
        );
    };

    $out[$code] = [
        'name' => $feature['properties']['name'] ?? $code,
        'bbox' => [$minLon, $minLat, $maxLon, $maxLat],
        'polygons' => array_map($round, $polygons),
    ];

    fwrite(STDERR, "ok {$code}\n");
}

$payload = [
    '_licence' => 'Boundaries derived from OpenStreetMap via hyperknot/country-levels-export. '.
        '© OpenStreetMap contributors, licensed under ODbL (https://opendatacommons.org/licenses/odbl/).',
    '_generated_by' => 'database/geo/build-canton-boundaries.php',
    'cantons' => $out,
];

$path = __DIR__.'/../../resources/geo/swiss-cantons.json';
@mkdir(dirname($path), 0755, true);
file_put_contents($path, json_encode($payload, JSON_UNESCAPED_SLASHES));

fwrite(STDERR, 'Wrote '.number_format(filesize($path))." bytes to {$path}\n");
