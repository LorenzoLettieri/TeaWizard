<?php

use App\Models\Team;
use App\Models\User;
use App\Support\Roles;
use Flux\Flux;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    public $teams;
    public $allUsers;
    public array $currentUserTeamIds = [];

    #[Validate('required|string|max:255')]
    public $name = '';

    #[Validate('nullable|string')]
    public $description = '';

    public ?Team $editingTeam = null;
    public ?Team $managingTeam = null;

    public function mount(): void
    {
        $this->loadTeams();
        $this->refreshCurrentUserTeams();
    }

    protected function isAdmin(): bool
    {
        return auth()->user()?->hasRole(Roles::ADMIN) ?? false;
    }

    protected function refreshCurrentUserTeams(): void
    {
        $this->currentUserTeamIds = auth()->user()
            ->teams()
            ->pluck('teams.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function loadTeams(): void
    {
        $this->teams = Team::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingTeam) {
            $this->editingTeam->update([
                'name' => $this->name,
                'description' => $this->description,
            ]);
            Flux::toast('Team updated successfully.');
        } else {
            Team::create([
                'name' => $this->name,
                'description' => $this->description,
            ]);
            Flux::toast('Team created successfully.');
        }

        $this->reset(['name', 'description', 'editingTeam']);
        $this->loadTeams();
        $this->modal('team-modal')->close();
    }

    public function edit(Team $team): void
    {
        $this->editingTeam = $team;
        $this->name = $team->name;
        $this->description = $team->description;
        $this->modal('team-modal')->show();
    }

    public function joinTeam(Team $team): void
    {
        $team->users()->syncWithoutDetaching([auth()->id()]);
        $this->refreshCurrentUserTeams();
        $this->loadTeams();
        Flux::toast('You joined the team.');
    }

    public function leaveTeam(Team $team): void
    {
        $team->users()->detach(auth()->id());

        if ($this->managingTeam?->is($team)) {
            $this->managingTeam = $team->fresh()->load('users');
        }

        $this->refreshCurrentUserTeams();
        $this->loadTeams();
        Flux::toast('You left the team.');
    }

    public function manageMembers(Team $team): void
    {
        abort_unless($this->isAdmin(), 403);

        $this->allUsers = User::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $this->managingTeam = $team->load('users');
        $this->modal('members-modal')->show();
    }

    public function attachUser(int $userId): void
    {
        abort_unless($this->isAdmin(), 403);

        $this->managingTeam?->users()->syncWithoutDetaching([$userId]);
        $this->managingTeam = $this->managingTeam?->fresh()->load('users');
        $this->loadTeams();
        $this->refreshCurrentUserTeams();
        Flux::toast('User added to team.');
    }

    public function detachUser(int $userId): void
    {
        abort_unless($this->isAdmin(), 403);

        $this->managingTeam?->users()->detach($userId);
        $this->managingTeam = $this->managingTeam?->fresh()->load('users');
        $this->loadTeams();
        $this->refreshCurrentUserTeams();
        Flux::toast('User removed from team.');
    }

    public function delete(Team $team): void
    {
        $team->delete();
        $this->loadTeams();
        $this->refreshCurrentUserTeams();
        Flux::toast('Team deleted successfully.');
    }

    public function cancel(): void
    {
        $this->reset(['name', 'description', 'editingTeam']);
        $this->modal('team-modal')->close();
    }
}; ?>

<div class="w-full">
    <flux:main container class="py-10">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold dark:text-white">Teams</h1>
                <p class="text-zinc-500 dark:text-zinc-400">Create teams freely, join any team, and let admins manage invites.</p>
            </div>

            <flux:modal.trigger name="team-modal">
                <flux:button variant="primary" icon="plus">Add Team</flux:button>
            </flux:modal.trigger>
        </div>

        <flux:separator class="my-6" />

        <div
            class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden shadow-sm">
            <table class="w-full text-left">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-6 py-3 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Members</th>
                        <th class="px-6 py-3 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Your Status</th>
                        <th class="px-6 py-3 text-xs font-semibold text-zinc-500 uppercase tracking-wider text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($teams as $team)
                        @php
                            $isMember = in_array($team->id, $currentUserTeamIds, true);
                        @endphp

                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="px-6 py-4 text-sm font-medium text-zinc-800 dark:text-white">{{ $team->name }}</td>
                            <td class="px-6 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ $team->description }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200">
                                    {{ $team->users_count }} users
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @if ($isMember)
                                    <flux:badge color="success" size="sm">Joined</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">Not joined</flux:badge>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($isMember)
                                        <flux:button variant="ghost" size="sm" wire:click="leaveTeam({{ $team->id }})">Leave</flux:button>
                                    @else
                                        <flux:button variant="primary" size="sm" wire:click="joinTeam({{ $team->id }})">Join</flux:button>
                                    @endif

                                    <flux:dropdown>
                                        <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm"></flux:button>
                                        <flux:menu>
                                            @if (auth()->user()?->hasRole(Roles::ADMIN))
                                                <flux:menu.item wire:click="manageMembers({{ $team->id }})" icon="user-plus">
                                                    Manage Members
                                                </flux:menu.item>
                                            @endif
                                            <flux:menu.item wire:click="edit({{ $team->id }})" icon="pencil-square">Edit</flux:menu.item>
                                            <flux:menu.item wire:click="delete({{ $team->id }})" icon="trash" variant="danger">
                                                Delete
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <flux:modal name="team-modal" class="md:w-[30rem]">
            <form wire:submit="save" class="space-y-6">
                <div>
                    <h2 class="text-lg font-bold dark:text-white">{{ $editingTeam ? 'Edit Team' : 'Add Team' }}</h2>
                    <p class="text-sm text-zinc-500">Fill in the details for the team.</p>
                </div>

                <flux:input wire:model="name" label="Name" placeholder="Team name..." />
                <flux:textarea wire:model="description" label="Description" placeholder="Optional description..." />

                <div class="flex">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Save</flux:button>
                </div>
            </form>
        </flux:modal>

        @if (auth()->user()?->hasRole(Roles::ADMIN))
            <flux:modal name="members-modal" class="md:w-[35rem]">
                <div class="space-y-6">
                    <div>
                        <h2 class="text-lg font-bold dark:text-white">Manage Members: {{ $managingTeam?->name }}</h2>
                        <p class="text-sm text-zinc-500">Only admins can add or remove members from a team.</p>
                    </div>

                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">Current Members</h3>
                        <div class="space-y-2">
                            @if ($managingTeam && $managingTeam->users->count() > 0)
                                @foreach ($managingTeam->users as $member)
                                    <div class="flex items-center justify-between p-2 bg-zinc-50 dark:bg-zinc-800 rounded-lg">
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="w-8 h-8 flex items-center justify-center rounded-full bg-zinc-200 dark:bg-zinc-700 text-xs font-bold text-zinc-600 dark:text-zinc-300">
                                                {{ $member->initials() }}
                                            </span>
                                            <span class="text-sm dark:text-zinc-300">{{ $member->name }}</span>
                                        </div>
                                        <flux:button variant="ghost" icon="x-mark" size="xs"
                                            wire:click="detachUser({{ $member->id }})" />
                                    </div>
                                @endforeach
                            @else
                                <p class="text-sm text-zinc-500 italic">No members in this team yet.</p>
                            @endif
                        </div>
                    </div>

                    <flux:separator />

                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">Add Members</h3>
                        <div class="space-y-2 max-h-60 overflow-y-auto pr-2">
                            @foreach ($allUsers ?? [] as $user)
                                @if (!$managingTeam || !$managingTeam->users->contains($user->id))
                                    <div
                                        class="flex items-center justify-between p-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 rounded-lg transition-colors group">
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="w-8 h-8 flex items-center justify-center rounded-full bg-zinc-200 dark:bg-zinc-700 text-xs font-bold text-zinc-600 dark:text-zinc-300">
                                                {{ $user->initials() }}
                                            </span>
                                            <span class="text-sm dark:text-zinc-300">{{ $user->name }}</span>
                                        </div>
                                        <flux:button variant="ghost" icon="plus" size="xs" wire:click="attachUser({{ $user->id }})" />
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="flex">
                        <flux:spacer />
                        <flux:modal.close>
                            <flux:button variant="ghost">Close</flux:button>
                        </flux:modal.close>
                    </div>
                </div>
            </flux:modal>
        @endif
    </flux:main>
</div>
