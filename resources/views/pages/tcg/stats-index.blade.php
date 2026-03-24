<?php

use App\Models\Archetype;
use App\Models\Result;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    public $filterTeam = '';
    public $filterPlayer = '';
    public $filterMyArchetype = '';
    public $filterOpponentArchetype = '';
    public $stats = [];
    public $userTeams;
    public $players;
    public $archetypes;
    public array $teamUserIdsByTeam = [];
    public array $allTeamUserIds = [];

    public function mount(): void
    {
        $this->loadSupportData();
        $this->calculateStats();
    }

    public function updated($property): void
    {
        if (! in_array($property, ['filterTeam', 'filterPlayer', 'filterMyArchetype', 'filterOpponentArchetype'], true)) {
            return;
        }

        $this->calculateStats();
    }

    protected function loadSupportData(): void
    {
        $this->userTeams = auth()->user()
            ->teams()
            ->with(['users:id,name'])
            ->orderBy('name')
            ->get(['teams.id', 'teams.name']);

        $this->teamUserIdsByTeam = $this->userTeams
            ->mapWithKeys(fn ($team) => [
                (int) $team->id => $team->users->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ])
            ->all();

        $this->allTeamUserIds = collect($this->teamUserIdsByTeam)
            ->flatten()
            ->unique()
            ->values()
            ->all();

        $this->players = User::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $this->archetypes = Archetype::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function selectedUserIds(): array
    {
        if ($this->filterPlayer) {
            return [(int) $this->filterPlayer];
        }

        if ($this->filterTeam) {
            return $this->teamUserIdsByTeam[(int) $this->filterTeam] ?? [];
        }

        return $this->allTeamUserIds;
    }

    protected function currentScopeLabel(): string
    {
        if ($this->filterPlayer) {
            return (string) ($this->players->firstWhere('id', (int) $this->filterPlayer)?->name ?? 'Selected player');
        }

        if ($this->filterTeam) {
            return (string) ($this->userTeams->firstWhere('id', (int) $this->filterTeam)?->name ?? 'Selected team');
        }

        return 'All your teams';
    }

    protected function buildFilteredQuery()
    {
        $userIds = $this->selectedUserIds();

        return Result::query()
            ->whereIn('results.user_id', $userIds)
            ->when($this->filterMyArchetype, function ($query) {
                $query->whereHas('deck', function ($deckQuery) {
                    $deckQuery->where('archetype_id', $this->filterMyArchetype);
                });
            })
            ->when($this->filterOpponentArchetype, function ($query) {
                $archetype = $this->archetypes->firstWhere('id', (int) $this->filterOpponentArchetype);

                if ($archetype) {
                    $query->where('opponent_deck', 'like', '%' . $archetype->name . '%');
                }
            });
    }

    protected function emptyStats(): array
    {
        return [
            'scope_label' => $this->currentScopeLabel(),
            'total' => 0,
            'wins' => 0,
            'losses' => 0,
            'draws' => 0,
            'winrate' => 0,
            'dice_winrate' => 0,
            'won_dice_winrate' => 0,
            'lost_dice_winrate' => 0,
            'matchups' => [],
            'meta_share' => [],
            'played_decks' => [],
            'heatmap' => [
                'columns' => [],
                'rows' => [],
                'overall' => [],
            ],
            'charts' => [],
        ];
    }

    protected function heatmapCell(array $values): array
    {
        $total = (int) ($values['total'] ?? 0);
        $wins = (int) ($values['wins'] ?? 0);
        $losses = (int) ($values['losses'] ?? 0);
        $draws = (int) ($values['draws'] ?? 0);
        $winrate = $total > 0 ? ($wins / $total) * 100 : null;

        if ($total === 0 || $winrate === null) {
            return [
                'total' => 0,
                'wins' => 0,
                'losses' => 0,
                'draws' => 0,
                'winrate' => null,
                'style' => 'background-color: rgba(244, 244, 245, 0.75); color: #a1a1aa;',
                'title' => 'No matches',
            ];
        }

        $distance = abs($winrate - 50) / 50;
        $sampleFactor = min($total / 10, 1);
        $strength = max(0.18, min(0.92, 0.22 + ($distance * 0.5) + ($sampleFactor * 0.2)));

        if ($winrate >= 50) {
            $style = sprintf('background-color: rgba(8, 145, 178, %.3F); color: %s;', $strength, $strength > 0.55 ? '#ecfeff' : '#164e63');
        } else {
            $style = sprintf('background-color: rgba(234, 88, 12, %.3F); color: %s;', $strength, $strength > 0.55 ? '#fff7ed' : '#7c2d12');
        }

        return [
            'total' => $total,
            'wins' => $wins,
            'losses' => $losses,
            'draws' => $draws,
            'winrate' => $winrate,
            'style' => $style,
            'title' => sprintf('Winrate: %.1f%% | Matches: %d | W-L-D: %d-%d-%d', $winrate, $total, $wins, $losses, $draws),
        ];
    }

    protected function buildHeatmap($baseQuery): array
    {
        $rawMatrix = (clone $baseQuery)
            ->join('decks', 'decks.id', '=', 'results.deck_id')
            ->leftJoin('archetypes', 'archetypes.id', '=', 'decks.archetype_id')
            ->selectRaw("
                COALESCE(archetypes.name, decks.name, 'Unknown') as my_archetype,
                results.opponent_deck as opponent_deck,
                COUNT(*) as total,
                SUM(CASE WHEN results.match_result = 'win' THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN results.match_result = 'loss' THEN 1 ELSE 0 END) as losses,
                SUM(CASE WHEN results.match_result = 'draw' THEN 1 ELSE 0 END) as draws
            ")
            ->groupBy('my_archetype', 'results.opponent_deck')
            ->get();

        $columns = $rawMatrix
            ->groupBy('opponent_deck')
            ->map(fn ($group, $label) => [
                'label' => (string) $label,
                'total' => (int) $group->sum('total'),
            ])
            ->sortByDesc('total')
            ->take(14)
            ->values();

        $columnLabels = $columns->pluck('label')->all();

        $rowLabels = $rawMatrix
            ->whereIn('opponent_deck', $columnLabels)
            ->groupBy('my_archetype')
            ->map(fn ($group, $label) => [
                'label' => (string) $label,
                'total' => (int) $group->sum('total'),
            ])
            ->sortByDesc('total')
            ->take(12)
            ->values()
            ->pluck('label')
            ->all();

        $lookup = $rawMatrix->keyBy(fn ($row) => $row->my_archetype . '||' . $row->opponent_deck);

        $rows = collect($rowLabels)->map(function ($rowLabel) use ($columnLabels, $lookup) {
            $cells = [];
            $rowTotals = ['total' => 0, 'wins' => 0, 'losses' => 0, 'draws' => 0];

            foreach ($columnLabels as $columnLabel) {
                $entry = $lookup->get($rowLabel . '||' . $columnLabel);

                $values = [
                    'total' => (int) ($entry->total ?? 0),
                    'wins' => (int) ($entry->wins ?? 0),
                    'losses' => (int) ($entry->losses ?? 0),
                    'draws' => (int) ($entry->draws ?? 0),
                ];

                $cells[$columnLabel] = $this->heatmapCell($values);

                $rowTotals['total'] += $values['total'];
                $rowTotals['wins'] += $values['wins'];
                $rowTotals['losses'] += $values['losses'];
                $rowTotals['draws'] += $values['draws'];
            }

            return [
                'label' => $rowLabel,
                'cells' => $cells,
                'overall' => $this->heatmapCell($rowTotals),
            ];
        })->all();

        $overall = [];

        foreach ($columnLabels as $columnLabel) {
            $columnTotals = ['total' => 0, 'wins' => 0, 'losses' => 0, 'draws' => 0];

            foreach ($rowLabels as $rowLabel) {
                $entry = $lookup->get($rowLabel . '||' . $columnLabel);

                $columnTotals['total'] += (int) ($entry->total ?? 0);
                $columnTotals['wins'] += (int) ($entry->wins ?? 0);
                $columnTotals['losses'] += (int) ($entry->losses ?? 0);
                $columnTotals['draws'] += (int) ($entry->draws ?? 0);
            }

            $overall[$columnLabel] = $this->heatmapCell($columnTotals);
        }

        return [
            'columns' => $columnLabels,
            'rows' => $rows,
            'overall' => $overall,
        ];
    }

    public function calculateStats(): void
    {
        $selectedUserIds = $this->selectedUserIds();

        if (empty($selectedUserIds)) {
            $this->stats = $this->emptyStats();

            return;
        }

        $baseQuery = $this->buildFilteredQuery();

        $summary = (clone $baseQuery)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN match_result = 'win' THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN match_result = 'loss' THEN 1 ELSE 0 END) as losses,
                SUM(CASE WHEN match_result = 'draw' THEN 1 ELSE 0 END) as draws,
                SUM(CASE WHEN dice_result = 'won' THEN 1 ELSE 0 END) as dice_wins,
                SUM(CASE WHEN dice_result = 'won' AND match_result = 'win' THEN 1 ELSE 0 END) as won_dice_wins,
                SUM(CASE WHEN dice_result = 'won' THEN 1 ELSE 0 END) as won_dice_total,
                SUM(CASE WHEN dice_result = 'lost' AND match_result = 'win' THEN 1 ELSE 0 END) as lost_dice_wins,
                SUM(CASE WHEN dice_result = 'lost' THEN 1 ELSE 0 END) as lost_dice_total
            ")
            ->first();

        $total = (int) ($summary->total ?? 0);

        if ($total === 0) {
            $this->stats = $this->emptyStats();

            return;
        }

        $wins = (int) ($summary->wins ?? 0);
        $losses = (int) ($summary->losses ?? 0);
        $draws = (int) ($summary->draws ?? 0);
        $diceWins = (int) ($summary->dice_wins ?? 0);
        $wonDiceTotal = (int) ($summary->won_dice_total ?? 0);
        $lostDiceTotal = (int) ($summary->lost_dice_total ?? 0);

        $wonDiceWinrate = $wonDiceTotal > 0
            ? (((int) ($summary->won_dice_wins ?? 0)) / $wonDiceTotal) * 100
            : 0;

        $lostDiceWinrate = $lostDiceTotal > 0
            ? (((int) ($summary->lost_dice_wins ?? 0)) / $lostDiceTotal) * 100
            : 0;

        $matchups = (clone $baseQuery)
            ->selectRaw("
                opponent_deck,
                COUNT(*) as total,
                SUM(CASE WHEN match_result = 'win' THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN match_result = 'loss' THEN 1 ELSE 0 END) as losses,
                SUM(CASE WHEN match_result = 'draw' THEN 1 ELSE 0 END) as draws
            ")
            ->groupBy('opponent_deck')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(function ($matchup) use ($total) {
                $matchTotal = max((int) $matchup->total, 1);

                return [
                    'opponent' => (string) $matchup->opponent_deck,
                    'total' => (int) $matchup->total,
                    'wins' => (int) $matchup->wins,
                    'losses' => (int) $matchup->losses,
                    'draws' => (int) $matchup->draws,
                    'winrate' => ((int) $matchup->wins / $matchTotal) * 100,
                    'share' => ((int) $matchup->total / $total) * 100,
                ];
            })
            ->values()
            ->toArray();

        $metaShare = (clone $baseQuery)
            ->selectRaw('opponent_deck as label, COUNT(*) as total')
            ->groupBy('opponent_deck')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->label,
                'total' => (int) $row->total,
                'share' => ((int) $row->total / $total) * 100,
            ])
            ->values()
            ->toArray();

        $playedDecks = (clone $baseQuery)
            ->join('decks', 'decks.id', '=', 'results.deck_id')
            ->selectRaw('decks.name as label, COUNT(*) as total')
            ->groupBy('decks.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->label,
                'total' => (int) $row->total,
                'share' => ((int) $row->total / $total) * 100,
            ])
            ->values()
            ->toArray();

        $heatmap = $this->buildHeatmap($baseQuery);

        $this->stats = [
            'scope_label' => $this->currentScopeLabel(),
            'total' => $total,
            'wins' => $wins,
            'losses' => $losses,
            'draws' => $draws,
            'winrate' => ($wins / $total) * 100,
            'dice_winrate' => ($diceWins / $total) * 100,
            'won_dice_winrate' => $wonDiceWinrate,
            'lost_dice_winrate' => $lostDiceWinrate,
            'matchups' => $matchups,
            'meta_share' => $metaShare,
            'played_decks' => $playedDecks,
            'heatmap' => $heatmap,
            'charts' => [
                'outcomes' => [
                    'labels' => ['Wins', 'Losses', 'Draws'],
                    'datasets' => [[
                        'data' => [$wins, $losses, $draws],
                        'backgroundColor' => ['#10b981', '#f43f5e', '#f59e0b'],
                        'borderWidth' => 0,
                    ]],
                ],
                'metaShare' => [
                    'labels' => array_map(fn ($row) => $row['label'], $metaShare),
                    'datasets' => [[
                        'label' => 'Matches',
                        'data' => array_map(fn ($row) => $row['total'], $metaShare),
                        'backgroundColor' => '#6366f1',
                        'borderRadius' => 8,
                    ]],
                ],
                'matchupWinrate' => [
                    'labels' => array_map(fn ($row) => $row['opponent'], $matchups),
                    'datasets' => [[
                        'label' => 'Winrate %',
                        'data' => array_map(fn ($row) => round($row['winrate'], 1), $matchups),
                        'backgroundColor' => '#14b8a6',
                        'borderRadius' => 8,
                    ]],
                ],
                'playedDecks' => [
                    'labels' => array_map(fn ($row) => $row['label'], $playedDecks),
                    'datasets' => [[
                        'label' => 'Matches',
                        'data' => array_map(fn ($row) => $row['total'], $playedDecks),
                        'backgroundColor' => '#8b5cf6',
                        'borderRadius' => 8,
                    ]],
                ],
            ],
        ];
    }
}; ?>

<div class="w-full" x-data="{
        charts: {},
        chartPayload: @js($stats['charts'] ?? []),
        renderCharts() {
            if (!window.TeaWizardCharts) {
                return;
            }

            const commonBarOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        ticks: { color: '#71717a' },
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#71717a' },
                        grid: { color: 'rgba(113, 113, 122, 0.12)' }
                    }
                }
            };

            this.charts.outcomes = window.TeaWizardCharts.render(this.$refs.outcomesChart, {
                type: 'doughnut',
                data: this.chartPayload.outcomes,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#71717a' } }
                    },
                    cutout: '68%'
                }
            });

            this.charts.metaShare = window.TeaWizardCharts.render(this.$refs.metaShareChart, {
                type: 'bar',
                data: this.chartPayload.metaShare,
                options: commonBarOptions
            });

            this.charts.matchupWinrate = window.TeaWizardCharts.render(this.$refs.matchupWinrateChart, {
                type: 'bar',
                data: this.chartPayload.matchupWinrate,
                options: {
                    ...commonBarOptions,
                    scales: {
                        ...commonBarOptions.scales,
                        y: {
                            ...commonBarOptions.scales.y,
                            suggestedMax: 100,
                            max: 100
                        }
                    }
                }
            });

            this.charts.playedDecks = window.TeaWizardCharts.render(this.$refs.playedDecksChart, {
                type: 'bar',
                data: this.chartPayload.playedDecks,
                options: commonBarOptions
            });
        }
    }" x-init="$nextTick(() => renderCharts())" x-effect="chartPayload = @js($stats['charts'] ?? []); $nextTick(() => renderCharts())">
    <flux:main class="max-w-[1850px] mx-auto py-10">
        <div class="mb-8">
            <h1 class="text-3xl font-bold dark:text-white">Stats</h1>
            <p class="text-zinc-500 dark:text-zinc-400 text-sm">Analyze your teams or jump directly to a player profile across the whole app.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <flux:select wire:model.live="filterTeam" label="Team Scope">
                <flux:select.option value="">All My Teams</flux:select.option>
                @foreach ($userTeams as $team)
                    <flux:select.option value="{{ $team->id }}">{{ $team->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filterPlayer" label="Player Override">
                <flux:select.option value="">No Player Override</flux:select.option>
                @foreach ($players as $player)
                    <flux:select.option value="{{ $player->id }}">{{ $player->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filterMyArchetype" label="Played Archetype">
                <flux:select.option value="">All Archetypes</flux:select.option>
                @foreach ($archetypes as $archetype)
                    <flux:select.option value="{{ $archetype->id }}">{{ $archetype->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filterOpponentArchetype" label="Opponent Archetype">
                <flux:select.option value="">All Opponents</flux:select.option>
                @foreach ($archetypes as $archetype)
                    <flux:select.option value="{{ $archetype->id }}">{{ $archetype->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="mb-8 flex items-center justify-between rounded-2xl border border-zinc-200 bg-zinc-50 px-5 py-4 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900/40 dark:text-zinc-300">
            <span>Current scope: <strong>{{ $stats['scope_label'] ?? 'All your teams' }}</strong></span>
            @if ($filterPlayer)
                <span class="text-xs uppercase tracking-[0.2em] text-zinc-400">Player filter overrides team scope</span>
            @endif
        </div>

        @if (($stats['total'] ?? 0) > 0)
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
                <div class="rounded-2xl bg-indigo-600 p-6 text-white shadow-lg shadow-indigo-500/20">
                    <p class="mb-1 text-sm font-medium text-indigo-100">General Winrate</p>
                    <h3 class="text-4xl font-black">{{ number_format($stats['winrate'], 1) }}%</h3>
                    <p class="mt-2 text-xs text-indigo-200">{{ $stats['total'] }} total matches</p>
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="mb-1 text-sm font-medium text-zinc-500 dark:text-zinc-400">Dice Winrate</p>
                    <h3 class="text-3xl font-bold dark:text-white">{{ number_format($stats['dice_winrate'], 1) }}%</h3>
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="mb-1 text-sm font-medium text-green-600 dark:text-green-400">Won Dice Winrate</p>
                    <h3 class="text-3xl font-bold dark:text-white">{{ number_format($stats['won_dice_winrate'], 1) }}%</h3>
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="mb-1 text-sm font-medium text-amber-600 dark:text-amber-400">Lost Dice Winrate</p>
                    <h3 class="text-3xl font-bold dark:text-white">{{ number_format($stats['lost_dice_winrate'], 1) }}%</h3>
                </div>
            </div>

            <div class="grid grid-cols-1 2xl:grid-cols-3 gap-8 mb-10">
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="mb-6">
                        <h2 class="text-lg font-bold dark:text-white">Outcome Split</h2>
                        <p class="text-sm text-zinc-500">Wins, losses and draws in the current scope.</p>
                    </div>
                    <div class="h-80">
                        <canvas x-ref="outcomesChart"></canvas>
                    </div>
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="mb-6">
                        <h2 class="text-lg font-bold dark:text-white">Encountered Deck Metashare</h2>
                        <p class="text-sm text-zinc-500">Top opponent decks faced in the filtered sample.</p>
                    </div>
                    <div class="h-80">
                        <canvas x-ref="metaShareChart"></canvas>
                    </div>
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="mb-6">
                        <h2 class="text-lg font-bold dark:text-white">Matchup Winrate</h2>
                        <p class="text-sm text-zinc-500">Winrate for the most played matchups.</p>
                    </div>
                    <div class="h-80">
                        <canvas x-ref="matchupWinrateChart"></canvas>
                    </div>
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 2xl:col-span-2">
                    <div class="mb-6">
                        <h2 class="text-lg font-bold dark:text-white">Played Deck Usage</h2>
                        <p class="text-sm text-zinc-500">Which decks are being logged most in the selected scope.</p>
                    </div>
                    <div class="h-80">
                        <canvas x-ref="playedDecksChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                <div class="xl:col-span-2 rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden">
                    <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                        <h2 class="text-lg font-bold dark:text-white">Matchup Table</h2>
                        <p class="text-sm text-zinc-500">Detailed breakdown for the most relevant pairings.</p>
                    </div>
                    <table class="w-full text-left">
                        <thead class="bg-zinc-50 text-[11px] font-bold uppercase text-zinc-500 dark:bg-zinc-800/50">
                            <tr>
                                <th class="px-6 py-3">Opponent Deck</th>
                                <th class="px-6 py-3 text-center">Share</th>
                                <th class="px-6 py-3 text-center">Total</th>
                                <th class="px-6 py-3 text-center">W/L/D</th>
                                <th class="px-6 py-3 text-right">Winrate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($stats['matchups'] as $matchup)
                                <tr class="text-sm">
                                    <td class="px-6 py-4 font-medium dark:text-zinc-200">{{ $matchup['opponent'] }}</td>
                                    <td class="px-6 py-4 text-center text-zinc-500">{{ number_format($matchup['share'], 1) }}%</td>
                                    <td class="px-6 py-4 text-center dark:text-zinc-400">{{ $matchup['total'] }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="font-bold text-green-600">{{ $matchup['wins'] }}</span>/
                                        <span>{{ $matchup['losses'] }}</span>/
                                        <span>{{ $matchup['draws'] }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-right font-bold {{ $matchup['winrate'] >= 50 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ number_format($matchup['winrate'], 1) }}%
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="space-y-6">
                    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <h2 class="mb-4 text-lg font-bold dark:text-white">Top Opponent Meta</h2>
                        <div class="space-y-4">
                            @foreach ($stats['meta_share'] as $row)
                                <div>
                                    <div class="mb-2 flex items-center justify-between text-sm">
                                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $row['label'] }}</span>
                                        <span class="text-zinc-500">{{ number_format($row['share'], 1) }}%</span>
                                    </div>
                                    <div class="h-2 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                                        <div class="h-full rounded-full bg-indigo-500" style="width: {{ $row['share'] }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <h2 class="mb-4 text-lg font-bold dark:text-white">Top Played Decks</h2>
                        <div class="space-y-4">
                            @foreach ($stats['played_decks'] as $row)
                                <div>
                                    <div class="mb-2 flex items-center justify-between text-sm">
                                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $row['label'] }}</span>
                                        <span class="text-zinc-500">{{ number_format($row['share'], 1) }}%</span>
                                    </div>
                                    <div class="h-2 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                                        <div class="h-full rounded-full bg-violet-500" style="width: {{ $row['share'] }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            @if (!empty($stats['heatmap']['columns']) && !empty($stats['heatmap']['rows']))
                <div class="mt-10 rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden">
                    <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
                        <h2 class="text-lg font-bold dark:text-white">Matchup Matrix</h2>
                        <p class="text-sm text-zinc-500">Hover a cell to inspect exact winrate and sample for each pairing.</p>
                    </div>

                    <div class="overflow-x-auto px-4 py-6">
                        <div class="inline-block min-w-full align-top">
                            <div class="grid gap-px bg-zinc-200 dark:bg-zinc-800"
                                style="grid-template-columns: 180px repeat({{ count($stats['heatmap']['columns']) }}, minmax(72px, 1fr));">
                                <div class="bg-white dark:bg-zinc-900"></div>
                                @foreach ($stats['heatmap']['columns'] as $column)
                                    <div class="flex h-28 items-end justify-center bg-white px-2 pb-3 dark:bg-zinc-900">
                                        <span class="origin-bottom-left -rotate-45 whitespace-nowrap text-xs font-medium text-zinc-500">
                                            {{ $column }}
                                        </span>
                                    </div>
                                @endforeach

                                @foreach ($stats['heatmap']['rows'] as $row)
                                    <div class="flex items-center bg-white px-4 py-3 text-sm font-semibold text-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                                        {{ $row['label'] }}
                                    </div>

                                    @foreach ($stats['heatmap']['columns'] as $column)
                                        @php($cell = $row['cells'][$column])
                                        <div class="group flex h-[72px] items-center justify-center bg-white p-1 text-xs font-semibold dark:bg-zinc-900">
                                            <div title="{{ $cell['title'] }}"
                                                class="flex h-full w-full items-center justify-center rounded-md transition-transform duration-150 group-hover:scale-[1.03]"
                                                style="{{ $cell['style'] }}">
                                                @if ($cell['winrate'] !== null)
                                                    {{ number_format($cell['winrate'], 0) }}%
                                                @else
                                                    -
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                @endforeach

                                <div class="flex items-center bg-zinc-50 px-4 py-3 text-sm font-bold text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100">
                                    Overall
                                </div>
                                @foreach ($stats['heatmap']['columns'] as $column)
                                    @php($cell = $stats['heatmap']['overall'][$column])
                                    <div class="group flex h-[72px] items-center justify-center bg-zinc-50 p-1 text-xs font-semibold dark:bg-zinc-800">
                                        <div title="{{ $cell['title'] }}"
                                            class="flex h-full w-full items-center justify-center rounded-md transition-transform duration-150 group-hover:scale-[1.03]"
                                            style="{{ $cell['style'] }}">
                                            @if ($cell['winrate'] !== null)
                                                {{ number_format($cell['winrate'], 0) }}%
                                            @else
                                                -
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @else
            <div class="flex flex-col items-center justify-center rounded-3xl border-2 border-dashed border-zinc-200 bg-white py-20 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:icon.chart-pie class="mb-4 text-zinc-300" size="xl" />
                <h3 class="text-xl font-bold dark:text-white">No data available</h3>
                <p class="text-zinc-500">Try adjusting your filters or record some testing results first.</p>
            </div>
        @endif
    </flux:main>
</div>
