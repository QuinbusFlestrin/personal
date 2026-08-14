<?php

namespace Tests\Feature\Public;

use App\Livewire\Events\EventSubmitForm;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_submit_an_event_for_review(): void
    {
        Livewire::test(EventSubmitForm::class)
            ->set('title', 'Community Flea Market')
            ->set('starts_at', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('submitter_email', 'guest@example.com')
            ->call('submit')
            ->assertHasNoErrors();

        $event = Event::where('title', 'Community Flea Market')->first();

        $this->assertNotNull($event);
        $this->assertSame(Event::STATUS_PENDING_REVIEW, $event->status);
        $this->assertNull($event->submitted_by_user_id);
        $this->assertSame('guest@example.com', $event->submitter_email);
    }

    public function test_submission_requires_a_title_and_email(): void
    {
        Livewire::test(EventSubmitForm::class)
            ->set('starts_at', now()->addWeek()->format('Y-m-d\TH:i'))
            ->call('submit')
            ->assertHasErrors(['title', 'submitter_email']);

        $this->assertSame(0, Event::count());
    }

    public function test_honeypot_field_silently_drops_the_submission(): void
    {
        Livewire::test(EventSubmitForm::class)
            ->set('title', 'Spam Event')
            ->set('starts_at', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('submitter_email', 'spam@example.com')
            ->set('website', 'http://spam-bot.example')
            ->call('submit');

        $this->assertSame(0, Event::count());
    }

    public function test_submitted_events_do_not_appear_on_the_public_events_page(): void
    {
        $pending = Event::factory()->pendingReview()->create(['title' => 'Hidden Pending Event']);
        $published = Event::factory()->published()->create(['title' => 'Visible Published Event']);

        $response = $this->get('/events');

        $response->assertSee('Visible Published Event');
        $response->assertDontSee('Hidden Pending Event');
    }
}
