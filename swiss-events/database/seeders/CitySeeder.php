<?php

namespace Database\Seeders;

use App\Models\Canton;
use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Cities exist so imported venues can be resolved to a canton — feeds almost
 * never publish one, and canton is the primary filter on /events.
 *
 * Deliberately not an exhaustive gazetteer: every cantonal capital (so all 26
 * cantons are reachable) plus the larger towns and tourist centres that
 * actually appear in event listings. Unmatched addresses fall back to matching
 * the canton name or code directly, and the list can grow without a migration.
 */
class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            // Cantonal capitals
            'Aarau' => 'AG', 'Appenzell' => 'AI', 'Herisau' => 'AR', 'Bern' => 'BE',
            'Liestal' => 'BL', 'Basel' => 'BS', 'Fribourg' => 'FR', 'Genève' => 'GE',
            'Glarus' => 'GL', 'Chur' => 'GR', 'Delémont' => 'JU', 'Luzern' => 'LU',
            'Neuchâtel' => 'NE', 'Stans' => 'NW', 'Sarnen' => 'OW', 'St. Gallen' => 'SG',
            'Schaffhausen' => 'SH', 'Solothurn' => 'SO', 'Schwyz' => 'SZ', 'Frauenfeld' => 'TG',
            'Bellinzona' => 'TI', 'Altdorf' => 'UR', 'Sion' => 'VS', 'Lausanne' => 'VD',
            'Zug' => 'ZG', 'Zürich' => 'ZH',

            // Other larger towns and frequent event locations
            'Winterthur' => 'ZH', 'Uster' => 'ZH', 'Dübendorf' => 'ZH', 'Dietikon' => 'ZH',
            'Wetzikon' => 'ZH', 'Kloten' => 'ZH', 'Rapperswil' => 'SG', 'Wil' => 'SG',
            'Biel' => 'BE', 'Thun' => 'BE', 'Köniz' => 'BE', 'Interlaken' => 'BE',
            'Gstaad' => 'BE', 'Burgdorf' => 'BE', 'Lugano' => 'TI', 'Locarno' => 'TI',
            'Ascona' => 'TI', 'Mendrisio' => 'TI', 'Vevey' => 'VD', 'Montreux' => 'VD',
            'Nyon' => 'VD', 'Morges' => 'VD', 'Renens' => 'VD', 'Yverdon-les-Bains' => 'VD',
            'Carouge' => 'GE', 'Vernier' => 'GE', 'Meyrin' => 'GE',
            'La Chaux-de-Fonds' => 'NE', 'Zermatt' => 'VS', 'Martigny' => 'VS',
            'Verbier' => 'VS', 'Brig' => 'VS', 'Sierre' => 'VS', 'Monthey' => 'VS',
            'Davos' => 'GR', 'St. Moritz' => 'GR', 'Arosa' => 'GR', 'Pontresina' => 'GR',
            'Baden' => 'AG', 'Wohlen' => 'AG', 'Rheinfelden' => 'AG', 'Olten' => 'SO',
            'Grenchen' => 'SO', 'Riehen' => 'BS', 'Allschwil' => 'BL', 'Muttenz' => 'BL',
            'Kreuzlingen' => 'TG', 'Arbon' => 'TG', 'Baar' => 'ZG', 'Cham' => 'ZG',
            'Emmen' => 'LU', 'Kriens' => 'LU', 'Bulle' => 'FR', 'Murten' => 'FR',
            'Einsiedeln' => 'SZ', 'Küssnacht' => 'SZ', 'Engelberg' => 'OW',
        ];

        $cantonIds = Canton::pluck('id', 'code');

        foreach ($cities as $name => $cantonCode) {
            if (! isset($cantonIds[$cantonCode])) {
                continue;
            }

            City::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'canton_id' => $cantonIds[$cantonCode]]
            );
        }
    }
}
