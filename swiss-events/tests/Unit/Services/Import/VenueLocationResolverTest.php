<?php

namespace Tests\Unit\Services\Import;

use App\Models\Canton;
use App\Models\City;
use App\Services\Import\Support\VenueLocationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueLocationResolverTest extends TestCase
{
    use RefreshDatabase;

    private Canton $zurich;

    private Canton $geneva;

    private City $zurichCity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zurich = Canton::create(['code' => 'ZH', 'name' => 'Zürich', 'slug' => 'zurich']);
        $this->geneva = Canton::create(['code' => 'GE', 'name' => 'Geneva', 'slug' => 'geneva']);
        $bern = Canton::create(['code' => 'BE', 'name' => 'Bern', 'slug' => 'bern']);

        $this->zurichCity = City::create(['name' => 'Zürich', 'slug' => 'zurich', 'canton_id' => $this->zurich->id]);
        City::create(['name' => 'Genève', 'slug' => 'geneve', 'canton_id' => $this->geneva->id]);
        City::create(['name' => 'Biel', 'slug' => 'biel', 'canton_id' => $bern->id]);
    }

    private function resolve(?string $address, ?string $venueName = null): array
    {
        return app(VenueLocationResolver::class)->resolve($address, $venueName);
    }

    public function test_it_matches_a_city_in_an_address(): void
    {
        $result = $this->resolve('Pelikanplatz 18, 8001 Zürich');

        $this->assertSame($this->zurichCity->id, $result['city_id']);
        $this->assertSame($this->zurich->id, $result['canton_id']);
    }

    public function test_it_matches_despite_missing_accents(): void
    {
        $result = $this->resolve('Bahnhofstrasse 1, 8001 Zurich');

        $this->assertSame($this->zurich->id, $result['canton_id']);
    }

    public function test_it_matches_exonyms(): void
    {
        foreach (['Rue du Rhône 1, 1204 Geneva', 'Genf', 'Ginevra'] as $address) {
            $this->assertSame(
                $this->geneva->id,
                $this->resolve($address)['canton_id'],
                "failed to resolve [{$address}]"
            );
        }
    }

    public function test_it_falls_back_to_the_canton_name_when_no_city_matches(): void
    {
        $result = $this->resolve('Some hamlet, Canton of Bern');

        $this->assertNull($result['city_id']);
        $this->assertNotNull($result['canton_id']);
    }

    public function test_it_falls_back_to_an_uppercase_canton_code(): void
    {
        $result = $this->resolve('Chemin des Vignes 4, 1299 GE');

        $this->assertSame($this->geneva->id, $result['canton_id']);
    }

    public function test_lowercase_code_like_text_does_not_produce_a_false_match(): void
    {
        // "ge" appears inside "Gestrasse" / "large" — matching it would put
        // venues in the wrong canton, which is worse than no canton at all.
        $result = $this->resolve('Grosse Gestrasse 4, Nowhere');

        $this->assertNull($result['canton_id']);
    }

    public function test_it_can_use_the_venue_name_when_the_address_is_missing(): void
    {
        $result = $this->resolve(null, 'Kaufleuten Zürich');

        $this->assertSame($this->zurich->id, $result['canton_id']);
    }

    public function test_it_returns_nothing_for_unrecognisable_text(): void
    {
        $this->assertSame(
            ['city_id' => null, 'canton_id' => null],
            $this->resolve('Somewhere else entirely')
        );
    }
}
