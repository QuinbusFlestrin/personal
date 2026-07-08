<?php

namespace App\Livewire\Events;

use App\Models\Canton;
use App\Models\Category;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Submit an event')]
class EventSubmitForm extends Component
{
    public string $title = '';

    public string $description = '';

    public ?int $category_id = null;

    public ?int $venue_id = null;

    public ?int $canton_id = null;

    public string $starts_at = '';

    public string $ends_at = '';

    public string $external_url = '';

    public string $submitter_name = '';

    public string $submitter_email = '';

    // Honeypot: real visitors never see or fill this field (hidden via CSS in the view).
    // Any submission with it filled in is treated as spam and silently dropped.
    public string $website = '';

    public bool $submitted = false;

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'canton_id' => ['nullable', 'exists:cantons,id'],
            'starts_at' => ['required', 'date', 'after_or_equal:today'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'external_url' => ['nullable', 'url', 'max:2048'],
            'submitter_name' => ['nullable', 'string', 'max:255'],
            'submitter_email' => ['required', 'email', 'max:255'],
        ];
    }

    public function submit(): void
    {
        if ($this->website !== '') {
            // Honeypot tripped — pretend success, do nothing.
            $this->submitted = true;

            return;
        }

        $key = 'event-submit:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('title', 'Too many submissions from this location. Please try again later.');

            return;
        }

        RateLimiter::hit($key, 3600);

        $validated = $this->validate();

        Event::create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['title'], $validated['starts_at']),
            'status' => Event::STATUS_PENDING_REVIEW,
            'submitted_by_user_id' => auth()->id(),
        ]);

        $this->reset(['title', 'description', 'category_id', 'venue_id', 'canton_id', 'starts_at', 'ends_at', 'external_url', 'submitter_name', 'submitter_email']);
        $this->submitted = true;
    }

    private function uniqueSlug(string $title, string $startsAt): string
    {
        $base = Str::slug($title.' '.Carbon::parse($startsAt)->toDateString());
        $slug = $base;
        $attempt = 1;

        while (Event::where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$attempt;
        }

        return $slug;
    }

    public function render()
    {
        return view('livewire.events.event-submit-form', [
            'categories' => Category::orderBy('sort_order')->get(),
            'venues' => Venue::where('status', 'published')->orderBy('name')->get(),
            'cantons' => Canton::orderBy('name')->get(),
        ]);
    }
}
