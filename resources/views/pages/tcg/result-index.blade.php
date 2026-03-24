<?php

use App\Models\Deck;
use App\Models\Result;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $filterUser = '';
    public $filterDeck = '';
    public $filterOpponent = '';
    public $sortField = 'date';
    public $sortDirection = 'desc';

    #[Validate('required|date')]
    public $date;

    #[Validate('required|string')]
    public $platform = 'TCGA random';

    #[Validate('required|exists:decks,id', as: 'Deck')]
    public $deck_id;

    #[Validate('required|string', as: 'Opponent Deck')]
    public $opponent_deck = '';

    #[Validate('required|in:won,lost')]
    public $dice_result = 'won';

    #[Validate('required|in:win,loss,draw')]
    public $game_1_result = 'win';

    #[Validate('nullable|in:win,loss,draw')]
    public $game_2_result;

    #[Validate('nullable|in:win,loss,draw')]
    public $game_3_result;

    #[Validate('nullable|string')]
    public $notes;

    #[Validate('nullable|string')]
    public $variance;

    #[Validate('nullable|string')]
    public $gameplan;

    #[Validate('nullable|string')]
    public $sideboard_notes;

    public $teamMembers;
    public $decks;
    public array $accessibleUserIds = [];

    public ?Result $editingResult = null;

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
        $this->loadSupportData();
    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['filterUser', 'filterDeck', 'filterOpponent', 'sortField', 'sortDirection'], true)) {
            $this->resetPage();
        }
    }

    protected function loadSupportData(): void
    {
        $user = auth()->user();
        $teamIds = $user->teams()->pluck('teams.id');

        $this->accessibleUserIds = DB::table('team_user')
            ->whereIn('team_id', $teamIds)
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->teamMembers = User::query()
            ->whereIn('id', $this->accessibleUserIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $this->decks = Deck::query()
            ->where('user_id', auth()->id())
            ->orderBy('name')
            ->get(['id', 'name', 'format']);
    }

    public function getResultsProperty()
    {
        return Result::query()
            ->with(['user', 'deck.archetype'])
            ->whereIn('user_id', $this->accessibleUserIds)
            ->when($this->filterUser, fn ($query) => $query->where('user_id', $this->filterUser))
            ->when($this->filterDeck, fn ($query) => $query->where('deck_id', $this->filterDeck))
            ->when($this->filterOpponent, fn ($query) => $query->where('opponent_deck', 'like', '%' . $this->filterOpponent . '%'))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(15);
    }

    public function save(): void
    {
        $validated = $this->validate();

        $wins = ($this->game_1_result === 'win' ? 1 : 0)
            + ($this->game_2_result === 'win' ? 1 : 0)
            + ($this->game_3_result === 'win' ? 1 : 0);

        $losses = ($this->game_1_result === 'loss' ? 1 : 0)
            + ($this->game_2_result === 'loss' ? 1 : 0)
            + ($this->game_3_result === 'loss' ? 1 : 0);

        $matchResult = $wins > $losses ? 'win' : ($wins < $losses ? 'loss' : 'draw');

        $data = array_merge($validated, [
            'match_result' => $matchResult,
            'user_id' => auth()->id(),
        ]);

        if ($this->editingResult) {
            $this->editingResult->update($data);
            Flux::toast('Result updated successfully.');
        } else {
            Result::create($data);
            Flux::toast('Result recorded successfully.');
        }

        $this->reset(['editingResult', 'deck_id', 'opponent_deck', 'notes', 'variance', 'gameplan', 'sideboard_notes']);
        $this->date = now()->format('Y-m-d');
        $this->modal('result-modal')->close();
    }

    public function edit(Result $result): void
    {
        $this->editingResult = $result;
        $this->date = $result->date->format('Y-m-d');
        $this->platform = $result->platform;
        $this->deck_id = $result->deck_id;
        $this->opponent_deck = $result->opponent_deck;
        $this->dice_result = $result->dice_result;
        $this->game_1_result = $result->game_1_result;
        $this->game_2_result = $result->game_2_result;
        $this->game_3_result = $result->game_3_result;
        $this->notes = $result->notes;
        $this->variance = $result->variance;
        $this->gameplan = $result->gameplan;
        $this->sideboard_notes = $result->sideboard_notes;

        $this->modal('result-modal')->show();
    }

    public function delete(Result $result): void
    {
        $result->delete();
        Flux::toast('Result deleted.');
    }

    public function sortBy($field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }
}; ?>

<div class="w-full">
    <flux:main class="max-w-[1800px] mx-auto py-10">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold dark:text-white">Team Results</h1>
                <p class="text-zinc-500 dark:text-zinc-400 text-sm">Testing analytics and results for all team members.</p>
            </div>

            <flux:modal.trigger name="result-modal">
                <flux:button variant="primary" icon="plus">New Entry</flux:button>
            </flux:modal.trigger>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <flux:select wire:model.live="filterUser" label="Member">
                <flux:select.option value="">All Team Members</flux:select.option>
                @foreach ($teamMembers as $member)
                    <flux:select.option value="{{ $member->id }}">{{ $member->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filterDeck" label="My Deck">
                <flux:select.option value="">All Your Decks</flux:select.option>
                @foreach ($decks as $deck)
                    <flux:select.option value="{{ $deck->id }}">{{ $deck->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model.live.debounce.300ms="filterOpponent" label="Opponent Deck" icon="magnifying-glass"
                placeholder="Search opponent..." />

            <div class="flex items-end">
                <flux:button variant="ghost" class="w-full"
                    wire:click="$set('filterUser', ''); $set('filterDeck', ''); $set('filterOpponent', '');">Clear
                    Filters</flux:button>
            </div>
        </div>

        <div
            class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-x-auto shadow-sm">
            <table class="w-full text-left border-collapse min-w-[1600px]">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-4 py-3 text-xs font-bold text-zinc-500 uppercase tracking-wider cursor-pointer"
                            wire:click="sortBy('date')">
                            Date @if ($sortField === 'date') @if ($sortDirection === 'asc') ↑ @else ↓ @endif @endif
                        </th>
                        <th class="px-4 py-3 text-xs font-bold text-zinc-500 uppercase tracking-wider">Player</th>
                        <th class="px-4 py-3 text-xs font-bold text-zinc-500 uppercase tracking-wider">Platform</th>
                        <th class="px-4 py-3 text-xs font-bold text-zinc-500 uppercase tracking-wider">My Deck</th>
                        <th class="px-4 py-3 text-xs font-bold text-zinc-500 uppercase tracking-wider">Opponent Deck</th>
                        <th class="px-4 py-3 text-xs font-bold text-zinc-500 uppercase tracking-wider text-center">Dice</th>
                        <th class="px-4 py-3 text-xs font-bold text-zinc-500 uppercase tracking-wider text-center">G1</th>
                        <th class="px-4 py-3 text-xs font-bold text-zinc-500 uppercase tracking-wider text-center">G2</th>
                        <th class="px-4 py-3 text-xs font-bold text-zinc-500 uppercase tracking-wider text-center">G3</th>
                        <th class="px-4 py-3 text-xs font-bold text-zinc-500 uppercase tracking-wider text-center">Match</th>
                        <th class="px-4 py-3 text-xs font-bold text-zinc-500 uppercase tracking-wider">Notes</th>
                        <th class="px-4 py-3 text-xs font-bold text-zinc-500 uppercase tracking-wider">Variance</th>
                        <th class="px-4 py-3 text-xs font-bold text-zinc-500 uppercase tracking-wider">Gameplan</th>
                        <th class="px-4 py-3 text-xs font-bold text-zinc-500 uppercase tracking-wider">Sideboard</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-zinc-500 uppercase tracking-wider text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->results as $result)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors group">
                            <td class="px-4 py-3 text-sm text-zinc-500 whitespace-nowrap">
                                {{ $result->date->format('d/m/Y') }}
                            </td>

                            <td class="px-4 py-3 text-sm font-semibold text-zinc-800 dark:text-zinc-200 whitespace-nowrap">
                                <span class="flex items-center gap-1.5">
                                    <span
                                        class="w-5 h-5 flex items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 text-[10px] font-bold">
                                        {{ $result->user->initials() }}
                                    </span>
                                    {{ $result->user->name }}
                                </span>
                            </td>

                            <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                {{ $result->platform }}
                            </td>

                            <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                {{ $result->deck->name }}
                            </td>

                            <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $result->opponent_deck }}
                            </td>

                            <td class="px-4 py-3 text-center">
                                <span @class([
                                    'text-[10px] font-bold px-1.5 py-0.5 rounded uppercase leading-none inline-block',
                                    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' => $result->dice_result === 'won',
                                    'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-400' => $result->dice_result === 'lost',
                                ])>
                                    {{ $result->dice_result }}
                                </span>
                            </td>

                            <td class="px-4 py-3 text-center">
                                <span @class([
                                    'inline-block w-6 py-0.5 rounded text-[10px] font-bold uppercase',
                                    'bg-green-100 text-green-800' => $result->game_1_result === 'win',
                                    'bg-red-100 text-red-800' => $result->game_1_result === 'loss',
                                    'bg-yellow-100 text-yellow-800' => $result->game_1_result === 'draw',
                                ])>
                                    {{ substr($result->game_1_result, 0, 1) }}
                                </span>
                            </td>

                            <td class="px-4 py-3 text-center">
                                @if ($result->game_2_result)
                                    <span @class([
                                        'inline-block w-6 py-0.5 rounded text-[10px] font-bold uppercase',
                                        'bg-green-100 text-green-800' => $result->game_2_result === 'win',
                                        'bg-red-100 text-red-800' => $result->game_2_result === 'loss',
                                        'bg-yellow-100 text-yellow-800' => $result->game_2_result === 'draw',
                                    ])>
                                        {{ substr($result->game_2_result, 0, 1) }}
                                    </span>
                                @else
                                    <span class="text-zinc-300">-</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-center">
                                @if ($result->game_3_result)
                                    <span @class([
                                        'inline-block w-6 py-0.5 rounded text-[10px] font-bold uppercase',
                                        'bg-green-100 text-green-800' => $result->game_3_result === 'win',
                                        'bg-red-100 text-red-800' => $result->game_3_result === 'loss',
                                        'bg-yellow-100 text-yellow-800' => $result->game_3_result === 'draw',
                                    ])>
                                        {{ substr($result->game_3_result, 0, 1) }}
                                    </span>
                                @else
                                    <span class="text-zinc-300">-</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-center align-middle">
                                <span @class([
                                    'inline-flex items-center px-2 py-0.5 rounded text-[10px] font-extrabold uppercase',
                                    'bg-green-600 text-white' => $result->match_result === 'win',
                                    'bg-red-600 text-white' => $result->match_result === 'loss',
                                    'bg-yellow-500 text-white' => $result->match_result === 'draw',
                                ])>
                                    {{ $result->match_result }}
                                </span>
                            </td>

                            <td class="px-4 py-3 text-sm text-zinc-500 max-w-[200px]">
                                @if ($result->notes)
                                    <flux:tooltip content="{{ $result->notes }}" position="top" align="start">
                                        <div class="truncate cursor-help">{{ $result->notes }}</div>
                                    </flux:tooltip>
                                @else
                                    <span class="text-zinc-300">-</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-sm text-zinc-500 max-w-[200px]">
                                @if ($result->variance)
                                    <flux:tooltip position="top" align="start">
                                        <div class="truncate text-amber-600 dark:text-amber-400 cursor-help">{{ $result->variance }}</div>

                                        <x-slot name="content">
                                            <div class="font-bold text-amber-400 mb-1 flex items-center gap-1">
                                                <flux:icon.exclamation-triangle size="xs" /> Variance
                                            </div>
                                            {{ $result->variance }}
                                        </x-slot>
                                    </flux:tooltip>
                                @else
                                    <span class="text-zinc-300">-</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-sm text-zinc-500 max-w-[200px]">
                                @if ($result->gameplan)
                                    <flux:tooltip position="top" align="start">
                                        <div class="truncate text-blue-600 dark:text-blue-400 cursor-help">{{ $result->gameplan }}</div>

                                        <x-slot name="content">
                                            <div class="font-bold text-blue-400 mb-1 flex items-center gap-1">
                                                <flux:icon.bolt size="xs" /> Gameplan
                                            </div>
                                            {{ $result->gameplan }}
                                        </x-slot>
                                    </flux:tooltip>
                                @else
                                    <span class="text-zinc-300">-</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-sm text-zinc-500 max-w-[200px]">
                                @if ($result->sideboard_notes)
                                    <flux:tooltip position="top" align="start">
                                        <div class="truncate text-indigo-600 dark:text-indigo-400 cursor-help">{{ $result->sideboard_notes }}</div>

                                        <x-slot name="content">
                                            <div class="font-bold text-indigo-400 mb-1 flex items-center gap-1">
                                                <flux:icon.clipboard-document-list size="xs" /> Sideboard
                                            </div>
                                            {{ $result->sideboard_notes }}
                                        </x-slot>
                                    </flux:tooltip>
                                @else
                                    <span class="text-zinc-300">-</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-right">
                                <flux:dropdown>
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm"></flux:button>
                                    <flux:menu>
                                        <flux:menu.item wire:click="edit({{ $result->id }})" icon="pencil-square">Edit
                                        </flux:menu.item>
                                        <flux:menu.item wire:click="delete({{ $result->id }})" icon="trash"
                                            variant="danger">Delete</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-6 py-12 text-center text-sm text-zinc-500 italic">
                                No entries found. Record your first testing result!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->results->hasPages())
            <div class="mt-4">
                {{ $this->results->links() }}
            </div>
        @endif

        <flux:modal name="result-modal" class="md:w-[48rem]">
            <form wire:submit="save" class="space-y-6">
                <div>
                    <h2 class="text-xl font-bold dark:text-white">
                        {{ $editingResult ? 'Update Testing Record' : 'New Testing Entry' }}
                    </h2>
                    <p class="text-sm text-zinc-500">Record all details for better team statistics and playtesting analysis.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input type="date" wire:model="date" label="Date of Testing" />
                    <flux:select wire:model="platform" label="Platform">
                        <flux:select.option value="TCGA random">TCGA random</flux:select.option>
                        <flux:select.option value="RnR ladder">RnR ladder</flux:select.option>
                        <flux:select.option value="Testing interno">Testing interno</flux:select.option>
                        <flux:select.option value="Torneo">Torneo</flux:select.option>
                    </flux:select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:select wire:model="deck_id" label="Your Deck" searchable placeholder="Select your deck...">
                        <flux:select.option value="">Choose a deck...</flux:select.option>
                        @foreach ($decks as $deck)
                            <flux:select.option value="{{ $deck->id }}">{{ $deck->name }} ({{ $deck->format }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="opponent_deck" label="Opponent Deck/Archetype"
                        placeholder="What were you playing against?" />
                </div>

                <flux:separator />

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 items-end">
                    <flux:select wire:model="dice_result" label="Dice Result">
                        <flux:select.option value="won">Won Dice</flux:select.option>
                        <flux:select.option value="lost">Lost Dice</flux:select.option>
                    </flux:select>
                    <flux:select wire:model="game_1_result" label="Game 1">
                        <flux:select.option value="win">Win</flux:select.option>
                        <flux:select.option value="loss">Loss</flux:select.option>
                        <flux:select.option value="draw">Draw</flux:select.option>
                    </flux:select>
                    <flux:select wire:model="game_2_result" label="Game 2">
                        <flux:select.option value="">N/A</flux:select.option>
                        <flux:select.option value="win">Win</flux:select.option>
                        <flux:select.option value="loss">Loss</flux:select.option>
                        <flux:select.option value="draw">Draw</flux:select.option>
                    </flux:select>
                    <flux:select wire:model="game_3_result" label="Game 3">
                        <flux:select.option value="">N/A</flux:select.option>
                        <flux:select.option value="win">Win</flux:select.option>
                        <flux:select.option value="loss">Loss</flux:select.option>
                        <flux:select.option value="draw">Draw</flux:select.option>
                    </flux:select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:textarea wire:model="notes" label="General Match Notes" rows="4"
                        placeholder="How did the games go?" />
                    <flux:textarea wire:model="variance" label="Variance Factors" rows="4"
                        placeholder="Flood, Screw, Luck, Misc..." />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:textarea wire:model="gameplan" label="Gameplan/Strategy" rows="3"
                        placeholder="Key takeaways for the matchup..." />
                    <flux:textarea wire:model="sideboard_notes" label="Sideboarding Notes" rows="3"
                        placeholder="In/Out choices made..." />
                </div>

                <div class="flex">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Save Report</flux:button>
                </div>
            </form>
        </flux:modal>
    </flux:main>
</div>
