<?php

use App\Models\RegistrationRequest;
use App\Models\User;
use App\Support\Roles;
use Flux\Flux;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new class extends Component {
    public $pendingRequests;
    public $reviewedRequests;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole(Roles::ADMIN), 403);

        $this->loadRequests();
    }

    public function approve(int $requestId): void
    {
        $request = RegistrationRequest::query()->findOrFail($requestId);

        if ($request->status !== RegistrationRequest::STATUS_PENDING) {
            Flux::toast('This request has already been reviewed.');

            return;
        }

        if (User::query()->where('email', $request->email)->exists()) {
            $request->update([
                'status' => RegistrationRequest::STATUS_REJECTED,
                'reviewed_at' => now(),
                'reviewed_by' => auth()->id(),
            ]);

            $this->loadRequests();
            Flux::toast('A user with this email already exists. Request rejected.');

            return;
        }

        $role = Role::query()
            ->where('name', Roles::USER)
            ->where('guard_name', 'web')
            ->first();

        if (! $role) {
            Flux::toast('The user role is missing. Seed roles before approving requests.');

            return;
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
            ]);

            $user->roles()->syncWithoutDetaching([$role->getKey()]);

            $request->update([
                'status' => RegistrationRequest::STATUS_APPROVED,
                'reviewed_at' => now(),
                'reviewed_by' => auth()->id(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            Flux::toast('Approval failed. Check application logs for details.');

            return;
        }

        $this->loadRequests();
        Flux::toast('Registration request approved.');
    }

    public function reject(int $requestId): void
    {
        $request = RegistrationRequest::query()->findOrFail($requestId);

        if ($request->status !== RegistrationRequest::STATUS_PENDING) {
            Flux::toast('This request has already been reviewed.');

            return;
        }

        $request->update([
            'status' => RegistrationRequest::STATUS_REJECTED,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);

        $this->loadRequests();
        Flux::toast('Registration request rejected.');
    }

    protected function loadRequests(): void
    {
        $this->pendingRequests = RegistrationRequest::query()
            ->where('status', RegistrationRequest::STATUS_PENDING)
            ->latest()
            ->get();

        $this->reviewedRequests = RegistrationRequest::query()
            ->with('reviewedBy')
            ->whereIn('status', [RegistrationRequest::STATUS_APPROVED, RegistrationRequest::STATUS_REJECTED])
            ->latest('reviewed_at')
            ->limit(20)
            ->get();
    }
}; ?>

<div class="w-full">
    <flux:main container class="py-10 space-y-8">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Access Requests</flux:heading>
                <flux:subheading>Approve or reject account requests before users can enter the testing center.</flux:subheading>
            </div>

            <flux:badge color="zinc" size="sm">{{ $pendingRequests->count() }} pending</flux:badge>
        </div>

        <section class="space-y-4">
            <div>
                <flux:heading size="lg" level="2">Pending</flux:heading>
                <flux:text class="text-zinc-500">These users are waiting for admin approval.</flux:text>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 overflow-hidden">
                <table class="w-full text-left">
                    <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Name</th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Email</th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Requested</th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($pendingRequests as $request)
                            <tr class="hover:bg-zinc-50 transition-colors dark:hover:bg-zinc-800/30">
                                <td class="px-6 py-4 text-sm font-medium text-zinc-900 dark:text-white">{{ $request->name }}</td>
                                <td class="px-6 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ $request->email }}</td>
                                <td class="px-6 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ $request->created_at->diffForHumans() }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        <flux:button variant="ghost" size="sm" wire:click="reject({{ $request->id }})">Reject</flux:button>
                                        <flux:button variant="primary" size="sm" wire:click="approve({{ $request->id }})">Approve</flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-10 text-center text-sm text-zinc-500">No pending requests.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-4">
            <div>
                <flux:heading size="lg" level="2">Recently Reviewed</flux:heading>
                <flux:text class="text-zinc-500">Latest approvals and rejections.</flux:text>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 overflow-hidden">
                <table class="w-full text-left">
                    <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Name</th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Email</th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Status</th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Reviewed by</th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Reviewed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($reviewedRequests as $request)
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-zinc-900 dark:text-white">{{ $request->name }}</td>
                                <td class="px-6 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ $request->email }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <flux:badge :color="$request->status === 'approved' ? 'success' : 'danger'" size="sm">
                                        {{ ucfirst($request->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ $request->reviewedBy?->name ?? 'System' }}</td>
                                <td class="px-6 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ $request->reviewed_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-zinc-500">No reviewed requests yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </flux:main>
</div>
