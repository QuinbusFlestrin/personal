<?php

namespace Database\Seeders;

use App\Models\Canton;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CantonSeeder extends Seeder
{
    /**
     * The 26 Swiss cantons.
     */
    public function run(): void
    {
        $cantons = [
            'AG' => 'Aargau',
            'AI' => 'Appenzell Innerrhoden',
            'AR' => 'Appenzell Ausserrhoden',
            'BE' => 'Bern',
            'BL' => 'Basel-Landschaft',
            'BS' => 'Basel-Stadt',
            'FR' => 'Fribourg',
            'GE' => 'Geneva',
            'GL' => 'Glarus',
            'GR' => 'Graubünden',
            'JU' => 'Jura',
            'LU' => 'Lucerne',
            'NE' => 'Neuchâtel',
            'NW' => 'Nidwalden',
            'OW' => 'Obwalden',
            'SG' => 'St. Gallen',
            'SH' => 'Schaffhausen',
            'SO' => 'Solothurn',
            'SZ' => 'Schwyz',
            'TG' => 'Thurgau',
            'TI' => 'Ticino',
            'UR' => 'Uri',
            'VD' => 'Vaud',
            'VS' => 'Valais',
            'ZG' => 'Zug',
            'ZH' => 'Zürich',
        ];

        foreach ($cantons as $code => $name) {
            Canton::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'slug' => Str::slug($name)]
            );
        }
    }
}
