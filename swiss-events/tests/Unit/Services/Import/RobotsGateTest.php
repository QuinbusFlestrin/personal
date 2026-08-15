<?php

namespace Tests\Unit\Services\Import;

use App\Services\Import\Support\RobotsGate;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RobotsGateTest extends TestCase
{
    private function gate(string $robots, int $status = 200): RobotsGate
    {
        Http::fake(['site.example.org/robots.txt' => Http::response($robots, $status)]);

        return new RobotsGate;
    }

    public function test_an_explicit_disallow_blocks_the_path(): void
    {
        $gate = $this->gate("User-agent: *\nDisallow: /private");

        $this->assertFalse($gate->allows('https://site.example.org/private/page'));
        $this->assertTrue($gate->allows('https://site.example.org/public/page'));
    }

    public function test_a_more_specific_allow_overrides_a_broader_disallow(): void
    {
        $gate = $this->gate("User-agent: *\nDisallow: /events\nAllow: /events/public");

        $this->assertFalse($gate->allows('https://site.example.org/events/secret'));
        $this->assertTrue($gate->allows('https://site.example.org/events/public/one'));
    }

    public function test_a_group_naming_our_bot_takes_precedence_over_the_wildcard(): void
    {
        $gate = $this->gate(
            "User-agent: *\nDisallow: /\n\nUser-agent: SwissEventsBot\nDisallow: /admin"
        );

        $this->assertTrue($gate->allows('https://site.example.org/agenda'));
        $this->assertFalse($gate->allows('https://site.example.org/admin/x'));
    }

    public function test_consecutive_user_agent_lines_share_one_rule_group(): void
    {
        $gate = $this->gate(
            "User-agent: SomeBot\nUser-agent: SwissEventsBot\nDisallow: /nope"
        );

        $this->assertFalse($gate->allows('https://site.example.org/nope'));
    }

    public function test_wildcards_and_end_anchors_are_honoured(): void
    {
        $gate = $this->gate("User-agent: *\nDisallow: /*.pdf$");

        $this->assertFalse($gate->allows('https://site.example.org/files/report.pdf'));
        $this->assertTrue($gate->allows('https://site.example.org/files/report.pdf.html'));
    }

    public function test_an_empty_disallow_permits_everything(): void
    {
        $gate = $this->gate("User-agent: *\nDisallow:");

        $this->assertTrue($gate->allows('https://site.example.org/anything'));
    }

    /**
     * A site with no robots.txt has stated no restrictions — treating that as a
     * prohibition would block most of the web.
     */
    public function test_a_missing_robots_file_permits_fetching(): void
    {
        $gate = $this->gate('Not Found', 404);

        $this->assertTrue($gate->allows('https://site.example.org/agenda'));
    }

    public function test_malformed_content_does_not_throw(): void
    {
        $gate = $this->gate("<<<not robots\n\n???");

        $this->assertTrue($gate->allows('https://site.example.org/agenda'));
    }

    public function test_robots_is_fetched_once_per_host(): void
    {
        $gate = $this->gate("User-agent: *\nDisallow: /private");

        $gate->allows('https://site.example.org/a');
        $gate->allows('https://site.example.org/b');
        $gate->allows('https://site.example.org/c');

        Http::assertSentCount(1);
    }
}
