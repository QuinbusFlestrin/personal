<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\EventResource\Pages\ListEvents;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_admin_panel(): void
    {
        $this->get('/admin/events')->assertRedirect('/admin/login');
    }

    public function test_members_cannot_access_the_admin_panel(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/events')->assertForbidden();
    }

    #[DataProvider('adminResourceUrls')]
    public function test_admin_can_view_each_resource_index(string $url): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get($url)->assertOk();
    }

    public static function adminResourceUrls(): array
    {
        return [
            ['/admin/sources'],
            ['/admin/events'],
            ['/admin/venues'],
            ['/admin/categories'],
            ['/admin/tags'],
        ];
    }

    public function test_admin_can_approve_a_pending_event_from_the_table_action(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->pendingReview()->create();

        $this->actingAs($admin);

        Livewire::test(ListEvents::class)
            ->callTableAction('approve', $event);

        $event->refresh();
        $this->assertSame(Event::STATUS_PUBLISHED, $event->status);
        $this->assertSame($admin->id, $event->reviewed_by_user_id);
        $this->assertNotNull($event->reviewed_at);
    }

    public function test_admin_can_reject_a_pending_event_from_the_table_action(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->pendingReview()->create();

        $this->actingAs($admin);

        Livewire::test(ListEvents::class)
            ->callTableAction('reject', $event);

        $event->refresh();
        $this->assertSame(Event::STATUS_REJECTED, $event->status);
    }
}
