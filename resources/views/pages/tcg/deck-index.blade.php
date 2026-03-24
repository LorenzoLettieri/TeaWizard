<?php

use App\Models\Archetype;
use App\Models\Deck;
use Flux\Flux;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    public bool $ready = false;
    public $myDecks;
    public $sharedTeamDecks;
    public $archetypes;
    public $availableTeams;

    #[Validate('required|string|max:255')]
    public $name = '';

    #[Validate('required|string|max:100')]
    public $format = '';

    #[Validate('nullable|url')]
    public $link = '';

    #[Validate('nullable|string')]
    public $notes = '';

    #[Validate('nullable|exists:archetypes,id')]
    public $archetype_id = '';

    #[Validate('array')]
    public $shared_team_ids = [];

    public ?Deck $editingDeck = null;

    public function mount(): void
    {
        $this->myDecks = collect();
        $this->sharedTeamDecks = collect();
        $this->archetypes = collect();
        $this->availableTeams = collect();
    }

    public function bootPage(): void
    {
        if ($this->ready) {
            return;
        }

        $this->loadPageData();
        $this->ready = true;
    }

    protected function loadPageData(): void
    {
        $this->myDecks = Deck::query()
            ->with(['archetype:id,name', 'teams:id,name'])
            ->where('user_id', auth()->id())
            ->orderBy('name')
            ->get(['id', 'user_id', 'name', 'format', 'link', 'notes', 'archetype_id']);

        $this->archetypes = Archetype::query()
            ->orderBy('name')
            ->get(['id', 'name', 'format']);

        $this->availableTeams = auth()->user()
            ->teams()
            ->orderBy('name')
            ->get(['teams.id', 'teams.name']);

        $this->sharedTeamDecks = auth()->user()
            ->teams()
            ->with([
                'decks' => fn ($query) => $query
                    ->with(['archetype:id,name', 'user:id,name'])
                    ->select(['decks.id', 'decks.user_id', 'decks.name', 'decks.format', 'decks.link', 'decks.notes', 'decks.archetype_id'])
                    ->orderBy('name'),
            ])
            ->orderBy('name')
            ->get(['teams.id', 'teams.name']);
    }

    protected function normalizedSharedTeamIds(): array
    {
        $allowedTeamIds = $this->availableTeams->pluck('id')->map(fn ($id) => (int) $id)->all();

        return collect($this->shared_team_ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => in_array($id, $allowedTeamIds, true))
            ->unique()
            ->values()
            ->all();
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'format' => $this->format,
            'link' => $this->link ?: null,
            'notes' => $this->notes ?: null,
            'archetype_id' => $this->archetype_id ?: null,
        ];

        if ($this->editingDeck) {
            abort_unless($this->editingDeck->user_id === auth()->id(), 403);

            $this->editingDeck->update($data);
            $deck = $this->editingDeck;
            Flux::toast('Deck updated successfully.');
        } else {
            $deck = Deck::create([
                'user_id' => auth()->id(),
                ...$data,
            ]);
            Flux::toast('Deck created successfully.');
        }

        $deck->teams()->sync($this->normalizedSharedTeamIds());

        $this->reset(['name', 'format', 'link', 'notes', 'archetype_id', 'shared_team_ids', 'editingDeck']);
        $this->loadPageData();
        $this->modal('deck-modal')->close();
    }

    public function edit(Deck $deck): void
    {
        abort_unless($deck->user_id === auth()->id(), 403);

        $this->editingDeck = $deck->load('teams:id');
        $this->name = $deck->name;
        $this->format = $deck->format;
        $this->link = $deck->link;
        $this->notes = $deck->notes;
        $this->archetype_id = $deck->archetype_id;
        $this->shared_team_ids = $deck->teams->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->modal('deck-modal')->show();
    }

    public function delete(Deck $deck): void
    {
        abort_unless($deck->user_id === auth()->id(), 403);

        $deck->delete();
        $this->loadPageData();
        Flux::toast('Deck deleted successfully.');
    }

    public function cancel(): void
    {
        $this->reset(['name', 'format', 'link', 'notes', 'archetype_id', 'shared_team_ids', 'editingDeck']);
        $this->modal('deck-modal')->close();
    }
}; ?>

<div class="w-full">
    <flux:main container class="py-10 space-y-10">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold dark:text-white">Decks</h1>
                <p class="text-zinc-500 dark:text-zinc-400">Manage your decks, keep notes, and share them with your teams.</p>
            </div>

            <flux:modal.trigger name="deck-modal">
                <flux:button variant="primary" icon="plus">Add Deck</flux:button>
            </flux:modal.trigger>
        </div>

        <div wire:init="bootPage" class="space-y-10">
            @if ($ready)
                <section class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold dark:text-white">Your Decks</h2>
                            <p class="text-sm text-zinc-500">Private and shared decks owned by you.</p>
                        </div>
                        <flux:badge color="zinc" size="sm">{{ $myDecks->count() }} total</flux:badge>
                    </div>

                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden shadow-sm">
                        <table class="w-full text-left">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <th class="px-6 py-3 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Format</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Archetype</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Notes</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Shared With</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-zinc-500 uppercase tracking-wider text-right"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @forelse ($myDecks as $deck)
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors">
                                        <td class="px-6 py-4 text-sm font-medium text-zinc-800 dark:text-white">
                                            <div class="flex items-center gap-2">
                                                {{ $deck->name }}
                                                @if ($deck->link)
                                                    <a href="{{ $deck->link }}" target="_blank" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                                                        <flux:icon.link size="sm" />
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ $deck->format }}</td>
                                        <td class="px-6 py-4 text-sm">
                                            @if ($deck->archetype)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200">
                                                    {{ $deck->archetype->name }}
                                                </span>
                                            @else
                                                <span class="text-zinc-400 italic">None</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-zinc-600 dark:text-zinc-400 max-w-xs">
                                            @if ($deck->notes)
                                                <flux:tooltip content="{{ $deck->notes }}" position="top" align="start">
                                                    <div class="truncate cursor-help">{{ $deck->notes }}</div>
                                                </flux:tooltip>
                                            @else
                                                <span class="text-zinc-400 italic">No notes</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm">
                                            <div class="flex flex-wrap gap-2">
                                                @forelse ($deck->teams as $team)
                                                    <flux:badge color="zinc" size="sm">{{ $team->name }}</flux:badge>
                                                @empty
                                                    <span class="text-zinc-400 italic">Private</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-right">
                                            <flux:dropdown>
                                                <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm"></flux:button>
                                                <flux:menu>
                                                    <flux:menu.item wire:click="edit({{ $deck->id }})" icon="pencil-square">Edit</flux:menu.item>
                                                    <flux:menu.item wire:click="delete({{ $deck->id }})" icon="trash" variant="danger">Delete</flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-10 text-center text-sm text-zinc-500">No decks yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="space-y-4">
                    <div>
                        <h2 class="text-lg font-bold dark:text-white">Shared Decks By Team</h2>
                        <p class="text-sm text-zinc-500">Decks shared inside the teams you are part of.</p>
                    </div>

                    <div class="grid gap-6">
                        @forelse ($sharedTeamDecks as $team)
                            <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 overflow-hidden">
                                <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                                    <div>
                                        <h3 class="font-semibold text-zinc-900 dark:text-white">{{ $team->name }}</h3>
                                        <p class="text-sm text-zinc-500">{{ $team->decks->count() }} shared deck{{ $team->decks->count() === 1 ? '' : 's' }}</p>
                                    </div>
                                </div>

                                @if ($team->decks->isNotEmpty())
                                    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                        @foreach ($team->decks as $deck)
                                            <div class="px-6 py-4">
                                                <div class="space-y-2 min-w-0">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <p class="font-medium text-zinc-900 dark:text-white">{{ $deck->name }}</p>
                                                        @if ($deck->archetype)
                                                            <flux:badge color="zinc" size="sm">{{ $deck->archetype->name }}</flux:badge>
                                                        @endif
                                                        @if ($deck->link)
                                                            <a href="{{ $deck->link }}" target="_blank" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                                                                <flux:icon.link size="sm" />
                                                            </a>
                                                        @endif
                                                    </div>
                                                    <p class="text-sm text-zinc-500">Format: {{ $deck->format ?: 'N/A' }} &middot; Owner: {{ $deck->user->name }}</p>
                                                    @if ($deck->notes)
                                                        <div class="rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                                            {{ $deck->notes }}
                                                        </div>
                                                    @else
                                                        <p class="text-sm italic text-zinc-400">No notes shared for this deck.</p>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="px-6 py-8 text-sm text-zinc-500">No shared decks in this team yet.</div>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700">
                                Join a team to see shared decks.
                            </div>
                        @endforelse
                    </div>
                </section>
            @else
                <section class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="h-6 w-28 animate-pulse rounded bg-zinc-200 dark:bg-zinc-700"></div>
                            <div class="mt-2 h-4 w-56 animate-pulse rounded bg-zinc-100 dark:bg-zinc-800"></div>
                        </div>
                        <div class="h-6 w-16 animate-pulse rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    </div>

                    <div class="space-y-3 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        @foreach (range(1, 5) as $placeholder)
                            <div class="h-14 animate-pulse rounded-lg bg-zinc-100 dark:bg-zinc-800"></div>
                        @endforeach
                    </div>
                </section>

                <section class="space-y-4">
                    <div>
                        <div class="h-6 w-44 animate-pulse rounded bg-zinc-200 dark:bg-zinc-700"></div>
                        <div class="mt-2 h-4 w-64 animate-pulse rounded bg-zinc-100 dark:bg-zinc-800"></div>
                    </div>

                    <div class="space-y-3">
                        @foreach (range(1, 2) as $placeholder)
                            <div class="h-32 animate-pulse rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"></div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <flux:modal name="deck-modal" class="md:w-[36rem]">
            <form wire:submit="save" class="space-y-6">
                <div>
                    <h2 class="text-lg font-bold dark:text-white">{{ $editingDeck ? 'Edit Deck' : 'Add Deck' }}</h2>
                    <p class="text-sm text-zinc-500">Deck details, notes, and optional team sharing.</p>
                </div>

                <flux:input wire:model="name" label="Name" placeholder="E.g. My Tournament Deck..." />
                <flux:input wire:model="format" label="Format" placeholder="E.g. Standard..." />
                <flux:input wire:model="link" label="Decklist URL" placeholder="https://..." />

                <flux:select wire:model="archetype_id" label="Archetype" placeholder="Select an archetype (optional)" searchable>
                    <flux:select.option value="">None</flux:select.option>
                    @foreach ($archetypes as $archetype)
                        <flux:select.option value="{{ $archetype->id }}">{{ $archetype->name }} ({{ $archetype->format }})</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:textarea wire:model="notes" label="Notes" rows="5" placeholder="Testing notes, sideboard plans, matchup reminders..." />

                <div class="space-y-3">
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Share With Teams</h3>
                        <p class="text-sm text-zinc-500">Choose one or more teams you belong to.</p>
                    </div>

                    @if ($availableTeams->isNotEmpty())
                        <div class="space-y-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            @foreach ($availableTeams as $team)
                                <label class="flex items-center gap-3 text-sm text-zinc-700 dark:text-zinc-300">
                                    <input type="checkbox" value="{{ $team->id }}" wire:model="shared_team_ids" class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500">
                                    <span>{{ $team->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-lg border border-dashed border-zinc-300 px-3 py-4 text-sm text-zinc-500 dark:border-zinc-700">
                            You are not part of any team yet, so this deck will stay private.
                        </div>
                    @endif
                </div>

                <div class="flex">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Save</flux:button>
                </div>
            </form>
        </flux:modal>
    </flux:main>
</div>
