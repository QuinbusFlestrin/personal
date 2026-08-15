<?php

namespace Tests\Unit\Services\Import;

use App\Services\Import\Support\CantonLocator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Checked against real landmarks whose canton is unambiguous, since a boundary
 * file that silently loaded but misplaced points would be worse than none.
 */
class CantonLocatorTest extends TestCase
{
    public static function landmarks(): array
    {
        return [
            'Zürich Hauptbahnhof' => [47.3779, 8.5403, 'ZH'],
            'Schöllenen Gorge' => [46.649147, 8.590251, 'UR'],
            'Jet d\'Eau, Geneva' => [46.2074, 6.1558, 'GE'],
            'Bundeshaus, Bern' => [46.9465, 7.4443, 'BE'],
            'Lugano old town' => [46.0037, 8.9511, 'TI'],
            'Basel Münster' => [47.5556, 7.5925, 'BS'],
            'Matterhorn' => [45.9763, 7.6586, 'VS'],
            'Lausanne cathedral' => [46.5231, 6.6356, 'VD'],
            'St. Gallen abbey' => [47.4232, 9.3771, 'SG'],
            'Chur old town' => [46.8508, 9.5320, 'GR'],
        ];
    }

    #[DataProvider('landmarks')]
    public function test_it_locates_known_landmarks(float $lat, float $lng, string $expected): void
    {
        $this->assertSame($expected, app(CantonLocator::class)->codeFor($lat, $lng));
    }

    public function test_it_returns_null_outside_switzerland(): void
    {
        // Milan — comfortably outside every canton.
        $this->assertNull(app(CantonLocator::class)->codeFor(45.4642, 9.1900));
    }

    public function test_it_returns_null_without_coordinates(): void
    {
        $this->assertNull(app(CantonLocator::class)->codeFor(null, null));
    }
}
