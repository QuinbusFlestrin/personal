<div class="max-w-2xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Submit an event</h1>
    <p class="text-gray-600 mb-6">Know about a concert, festival, exhibition or other event happening in Switzerland? Share it here &mdash; it will be reviewed before it goes live.</p>

    @if ($submitted)
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-4">
            Thanks! Your event has been submitted and is now awaiting review.
        </div>
    @else
        <form wire:submit="submit" class="bg-white shadow rounded-lg p-6 space-y-4">
            {{-- Honeypot: hidden from real users via CSS, bots tend to fill every field --}}
            <div style="position:absolute; left:-9999px;" aria-hidden="true">
                <label for="website">Leave this field empty</label>
                <input type="text" id="website" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Title *</label>
                <input type="text" wire:model="title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Description</label>
                <textarea wire:model="description" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                @error('description') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Category</label>
                    <select wire:model="category_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Select...</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Canton</label>
                    <select wire:model="canton_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Select...</option>
                        @foreach ($cantons as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Venue</label>
                <select wire:model="venue_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Not listed / not applicable</option>
                    @foreach ($venues as $venue)
                        <option value="{{ $venue->id }}">{{ $venue->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Starts at *</label>
                    <input type="datetime-local" wire:model="starts_at" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @error('starts_at') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Ends at</label>
                    <input type="datetime-local" wire:model="ends_at" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @error('ends_at') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Link to official event page</label>
                <input type="url" wire:model="external_url" placeholder="https://..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                @error('external_url') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <hr class="my-4">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Your name</label>
                    <input type="text" wire:model="submitter_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Your email *</label>
                    <input type="email" wire:model="submitter_email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @error('submitter_email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md text-sm text-white hover:bg-indigo-700">
                Submit for review
            </button>
        </form>
    @endif
</div>
