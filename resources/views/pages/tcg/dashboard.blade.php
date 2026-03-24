<?php

use App\Models\Deck;
use App\Models\RegistrationRequest;
use App\Models\Result;
use App\Models\Team;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    public array $overview = [];
    public $recentResults;
    public $teamStandings;
    public $sharedDecks;
    public $teams;
    public $pendingRequests;
    public array $quickActions = [];

    public function mount(): void
    {
        $this->loadDashboard();
    }

    protected function loadDashboard(): void
    {
        $user = auth()->user();
        $teamIds = $user->teams()->pluck('teams.id');
        $teamUserIds = DB::table('team_user')
            ->whereIn('team_id', $teamIds)
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $personalQuery = Result::query()
            ->where('user_id', $user->id);

        $lastTenResults = (clone $personalQuery)
            ->latest('date')
            ->latest('id')
            ->limit(10)
            ->get(['id', 'match_result']);

        $lastTenTotal = $lastTenResults->count();
        $lastTenWins = $lastTenResults->where('match_result', 'win')->count();
        $lastTenWinrate = $lastTenTotal > 0 ? ($lastTenWins / $lastTenTotal) * 100 : 0;

        $favoriteDeck = Deck::query()
            ->where('user_id', $user->id)
            ->with('archetype:id,name')
            ->withCount('results')
            ->orderByDesc('results_count')
            ->orderBy('name')
            ->first();

        $focusMatchup = (clone $personalQuery)
            ->selectRaw("
                opponent_deck,
                COUNT(*) as total,
                SUM(CASE WHEN match_result = 'win' THEN 1 ELSE 0 END) as wins
            ")
            ->groupBy('opponent_deck')
            ->havingRaw('COUNT(*) >= 2')
            ->orderByRaw("SUM(CASE WHEN match_result = 'win' THEN 1 ELSE 0 END) / COUNT(*) asc")
            ->orderByDesc('total')
            ->first();

        $recentResults = Result::query()
            ->with(['user:id,name', 'deck:id,name,archetype_id', 'deck.archetype:id,name'])
            ->whereIn('user_id', $teamUserIds)
            ->latest('date')
            ->latest('id')
            ->limit(8)
            ->get();

        $standingRows = Result::query()
            ->whereIn('user_id', $teamUserIds)
            ->selectRaw("
                user_id,
                COUNT(*) as total,
                SUM(CASE WHEN match_result = 'win' THEN 1 ELSE 0 END) as wins
            ")
            ->groupBy('user_id')
            ->orderByDesc('wins')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->keyBy('user_id');

        $teamStandings = User::query()
            ->whereIn('id', $standingRows->keys())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($member) use ($standingRows) {
                $row = $standingRows[$member->id];
                $total = max((int) $row->total, 1);

                return [
                    'name' => $member->name,
                    'initials' => $member->initials(),
                    'total' => (int) $row->total,
                    'wins' => (int) $row->wins,
                    'winrate' => ((int) $row->wins / $total) * 100,
                ];
            })
            ->sortByDesc('winrate')
            ->values();

        $sharedDecks = Team::query()
            ->whereIn('teams.id', $teamIds)
            ->with([
                'decks' => fn ($query) => $query
                    ->with(['user:id,name', 'archetype:id,name'])
                    ->latest('deck_team.created_at')
                    ->limit(3),
            ])
            ->orderBy('name')
            ->get(['teams.id', 'teams.name']);

        $teams = Team::query()
            ->whereIn('id', $teamIds)
            ->withCount('users')
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        $sharedDeckCount = Deck::query()
            ->where('user_id', $user->id)
            ->whereHas('teams')
            ->count();

        $this->overview = [
            'last_ten_winrate' => $lastTenWinrate,
            'last_ten_delta' => $lastTenWinrate - 50,
            'favorite_deck' => $favoriteDeck?->name ?? 'No deck data yet',
            'favorite_deck_meta' => $favoriteDeck?->archetype?->name ?? ($favoriteDeck ? 'No archetype linked' : 'Create a deck to see this'),
            'total_games' => (clone $personalQuery)->count(),
            'teams_count' => count($teamIds),
            'shared_decks_count' => $sharedDeckCount,
            'focus_matchup' => $focusMatchup ? [
                'opponent' => (string) $focusMatchup->opponent_deck,
                'total' => (int) $focusMatchup->total,
                'winrate' => ((int) $focusMatchup->wins / max((int) $focusMatchup->total, 1)) * 100,
            ] : null,
        ];

        $this->recentResults = $recentResults;
        $this->teamStandings = $teamStandings;
        $this->sharedDecks = $sharedDecks;
        $this->teams = $teams;
        $this->pendingRequests = $user->hasRole(Roles::ADMIN)
            ? RegistrationRequest::query()->where('status', RegistrationRequest::STATUS_PENDING)->count()
            : 0;

        $this->quickActions = array_values(array_filter([
            ['label' => 'Log Results', 'href' => route('results.index'), 'description' => 'Record testing sessions and outcomes.'],
            ['label' => 'Manage Decks', 'href' => route('decks.index'), 'description' => 'Update deck notes and team sharing.'],
            ['label' => 'Open Stats', 'href' => route('stats.index'), 'description' => 'Inspect matchup data and meta trends.'],
            ['label' => 'Browse Teams', 'href' => route('teams.index'), 'description' => 'Create teams or join existing groups.'],
            $user->hasRole(Roles::ADMIN) ? ['label' => 'Review Access', 'href' => route('admin.registration-requests'), 'description' => 'Process pending account requests.'] : null,
        ]));
    }
}; ?>

<div class="w-full">
    <flux:main class="max-w-[1800px] mx-auto py-10 space-y-10">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <flux:heading size="xl" level="1">Testing Command Center</flux:heading>
                <flux:subheading>Live overview of your testing activity, team performance, and next actions.</flux:subheading>
            </div>

            <div class="flex flex-wrap gap-3">
                <flux:button variant="primary" href="{{ route('results.index') }}" icon="plus">Record New Result</flux:button>
                <flux:button variant="ghost" href="{{ route('stats.index') }}" icon="chart-bar">Open Stats</flux:button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-6">
            <div class="rounded-2xl bg-indigo-600 p-6 text-white shadow-lg shadow-indigo-500/20">
                <p class="text-sm font-medium text-indigo-100">Your Winrate (Last 10)</p>
                <div class="mt-3 flex items-end gap-3">
                    <h2 class="text-4xl font-black">{{ number_format($overview['last_ten_winrate'], 1) }}%</h2>
                    <span class="rounded-full bg-white/15 px-2 py-1 text-xs font-semibold text-indigo-50">
                        {{ $overview['last_ten_delta'] >= 0 ? '+' : '' }}{{ number_format($overview['last_ten_delta'], 1) }} vs 50%
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Most Played Deck</p>
                <h2 class="mt-3 text-xl font-bold dark:text-white">{{ $overview['favorite_deck'] }}</h2>
                <p class="mt-2 text-sm text-zinc-500">{{ $overview['favorite_deck_meta'] }}</p>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Games Logged</p>
                <h2 class="mt-3 text-4xl font-black text-zinc-900 dark:text-white">{{ $overview['total_games'] }}</h2>
                <p class="mt-2 text-sm text-zinc-500">Personal testing entries recorded.</p>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Your Teams</p>
                <h2 class="mt-3 text-4xl font-black text-zinc-900 dark:text-white">{{ $overview['teams_count'] }}</h2>
                <p class="mt-2 text-sm text-zinc-500">{{ $overview['shared_decks_count'] }} shared deck{{ $overview['shared_decks_count'] === 1 ? '' : 's' }}</p>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 shadow-sm dark:border-amber-900/30 dark:bg-amber-900/20">
                <p class="text-sm font-medium text-amber-700 dark:text-amber-300">Current Focus</p>
                @if ($overview['focus_matchup'])
                    <h2 class="mt-3 text-xl font-bold text-amber-900 dark:text-amber-100">{{ $overview['focus_matchup']['opponent'] }}</h2>
                    <p class="mt-2 text-sm text-amber-800 dark:text-amber-200">
                        {{ number_format($overview['focus_matchup']['winrate'], 1) }}% winrate across {{ $overview['focus_matchup']['total'] }} matches.
                    </p>
                @else
                    <h2 class="mt-3 text-xl font-bold text-amber-900 dark:text-amber-100">Need more data</h2>
                    <p class="mt-2 text-sm text-amber-800 dark:text-amber-200">Play at least a couple of repeated matchups to surface a focus area.</p>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            <div class="xl:col-span-2 space-y-8">
                <section class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden">
                    <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                        <div>
                            <h2 class="text-lg font-bold dark:text-white">Recent Testing Activity</h2>
                            <p class="text-sm text-zinc-500">Latest logs from you and your teams.</p>
                        </div>
                        <flux:button variant="ghost" size="sm" href="{{ route('results.index') }}">View all logs</flux:button>
                    </div>

                    <table class="w-full text-left">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-700">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">When</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Player</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Deck</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Matchup</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 text-right">Result</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($recentResults as $result)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="px-6 py-4 text-sm text-zinc-500 whitespace-nowrap">{{ $result->date->diffForHumans() }}</td>
                                    <td class="px-6 py-4 text-sm font-medium text-zinc-900 dark:text-white">{{ $result->user->name }}</td>
                                    <td class="px-6 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ $result->deck->name }}
                                        @if ($result->deck->archetype)
                                            <span class="text-zinc-400">· {{ $result->deck->archetype->name }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ $result->opponent_deck }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <flux:badge :color="$result->match_result === 'win' ? 'success' : ($result->match_result === 'loss' ? 'danger' : 'warning')" size="sm">
                                            {{ ucfirst($result->match_result) }}
                                        </flux:badge>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-sm text-zinc-500">No testing activity yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>

                <section class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden">
                    <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                        <h2 class="text-lg font-bold dark:text-white">Team Standings</h2>
                        <p class="text-sm text-zinc-500">Current winrate ranking inside your shared team scope.</p>
                    </div>

                    <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($teamStandings as $standing)
                            <div class="flex items-center justify-between px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-100 text-sm font-bold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                        {{ $standing['initials'] }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-zinc-900 dark:text-white">{{ $standing['name'] }}</p>
                                        <p class="text-sm text-zinc-500">{{ $standing['wins'] }} wins across {{ $standing['total'] }} matches</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-bold {{ $standing['winrate'] >= 50 ? 'text-emerald-600' : 'text-rose-600' }}">
                                        {{ number_format($standing['winrate'], 1) }}%
                                    </p>
                                </div>
                            </div>
                        @empty
                            <div class="px-6 py-10 text-center text-sm text-zinc-500">No standings yet. Add results to rank your group.</div>
                        @endforelse
                    </div>
                </section>
            </div>

            <div class="space-y-8">
                <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="text-lg font-bold dark:text-white">Quick Actions</h2>
                    <div class="mt-5 space-y-3">
                        @foreach ($quickActions as $action)
                            <a href="{{ $action['href'] }}"
                                class="block rounded-xl border border-zinc-200 px-4 py-3 transition-colors hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:border-zinc-700 dark:hover:bg-zinc-800/60">
                                <p class="font-medium text-zinc-900 dark:text-white">{{ $action['label'] }}</p>
                                <p class="mt-1 text-sm text-zinc-500">{{ $action['description'] }}</p>
                            </a>
                        @endforeach
                    </div>
                </section>

                @if ($pendingRequests)
                    <section class="rounded-2xl border border-sky-200 bg-sky-50 p-6 shadow-sm dark:border-sky-900/30 dark:bg-sky-900/20">
                        <h2 class="text-lg font-bold text-sky-900 dark:text-sky-100">Admin Queue</h2>
                        <p class="mt-3 text-sm text-sky-800 dark:text-sky-200">
                            {{ $pendingRequests }} access request{{ $pendingRequests === 1 ? '' : 's' }} waiting for review.
                        </p>
                        <flux:button class="mt-4" variant="primary" href="{{ route('admin.registration-requests') }}">Open Review Panel</flux:button>
                    </section>
                @endif

                <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="text-lg font-bold dark:text-white">Your Teams</h2>
                    <div class="mt-5 space-y-4">
                        @forelse ($teams as $team)
                            <div class="rounded-xl bg-zinc-50 px-4 py-3 dark:bg-zinc-800/60">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <p class="font-medium text-zinc-900 dark:text-white">{{ $team->name }}</p>
                                        <p class="text-sm text-zinc-500">{{ $team->users_count }} members</p>
                                    </div>
                                    <flux:button variant="ghost" size="sm" href="{{ route('teams.index') }}">Open</flux:button>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-zinc-500">You are not in any team yet.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="text-lg font-bold dark:text-white">Shared Decks Snapshot</h2>
                    <div class="mt-5 space-y-5">
                        @forelse ($sharedDecks as $team)
                            @if ($team->decks->isNotEmpty())
                                <div>
                                    <div class="mb-3 flex items-center justify-between">
                                        <p class="font-medium text-zinc-900 dark:text-white">{{ $team->name }}</p>
                                        <span class="text-xs uppercase tracking-[0.16em] text-zinc-400">{{ $team->decks->count() }} shown</span>
                                    </div>
                                    <div class="space-y-2">
                                        @foreach ($team->decks as $deck)
                                            <div class="rounded-xl bg-zinc-50 px-4 py-3 dark:bg-zinc-800/60">
                                                <p class="font-medium text-zinc-900 dark:text-white">{{ $deck->name }}</p>
                                                <p class="mt-1 text-sm text-zinc-500">
                                                    {{ $deck->user->name }}
                                                    @if ($deck->archetype)
                                                        · {{ $deck->archetype->name }}
                                                    @endif
                                                </p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @empty
                            <p class="text-sm text-zinc-500">No shared decks visible yet.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </flux:main>
</div>
