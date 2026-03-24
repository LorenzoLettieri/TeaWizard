<?php

use App\Models\Archetype;
use Livewire\Component;
use Livewire\Attributes\Validate;
use Flux\Flux;

new class extends Component {
    public $archetypes;

    #[Validate('required|string|max:255')]
    public $name = '';

    #[Validate('required|string|max:100')]
    public $format = '';

    #[Validate('nullable|string')]
    public $description = '';

    public ?Archetype $editingArchetype = null;

    public function mount()
    {
        $this->loadArchetypes();
    }

    public function loadArchetypes()
    {
        $this->archetypes = Archetype::all();
    }

    public function save()
    {
        $this->validate();

        if ($this->editingArchetype) {
            $this->editingArchetype->update([
                'name' => $this->name,
                'format' => $this->format,
                'description' => $this->description,
            ]);
            Flux::toast('Archetype updated successfully.');
        } else {
            Archetype::create([
                'name' => $this->name,
                'format' => $this->format,
                'description' => $this->description,
            ]);
            Flux::toast('Archetype created successfully.');
        }

        $this->reset(['name', 'format', 'description', 'editingArchetype']);
        $this->loadArchetypes();
        $this->modal('archetype-modal')->close();
    }

    public function edit(Archetype $archetype)
    {
        $this->editingArchetype = $archetype;
        $this->name = $archetype->name;
        $this->format = $archetype->format;
        $this->description = $archetype->description;
        $this->modal('archetype-modal')->show();
    }

    public function delete(Archetype $archetype)
    {
        $archetype->delete();
        $this->loadArchetypes();
        Flux::toast('Archetype deleted successfully.');
    }

    public function cancel()
    {
        $this->reset(['name', 'format', 'description', 'editingArchetype']);
        $this->modal('archetype-modal')->close();
    }
}; ?>

<div class="w-full">
    <flux:main container class="py-10">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold dark:text-white">Archetypes</h1>
                <p class="text-zinc-500 dark:text-zinc-400">Manage global deck archetypes.</p>
            </div>

            <flux:modal.trigger name="archetype-modal">
                <flux:button variant="primary" icon="plus">Add Archetype</flux:button>
            </flux:modal.trigger>
        </div>

        <flux:separator class="my-6" />

        <div
            class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden shadow-sm">
            <table class="w-full text-left">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-6 py-3 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Format</th>
                        <th class="px-6 py-3 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Description
                        </th>
                        <th class="px-6 py-3 text-xs font-semibold text-zinc-500 uppercase tracking-wider text-right">
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($archetypes as $archetype)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="px-6 py-4 text-sm font-medium text-zinc-800 dark:text-white">{{ $archetype->name }}
                            </td>
                            <td class="px-6 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                    {{ $archetype->format }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ $archetype->description }}
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <flux:dropdown>
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm"></flux:button>
                                    <flux:menu>
                                        <flux:menu.item wire:click="edit({{ $archetype->id }})" icon="pencil-square">Edit
                                        </flux:menu.item>
                                        <flux:menu.item wire:click="delete({{ $archetype->id }})" icon="trash"
                                            variant="danger">Delete</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <flux:modal name="archetype-modal" class="md:w-[30rem]">
            <form wire:submit="save" class="space-y-6">
                <div>
                    <h2 class="text-lg font-bold dark:text-white">
                        {{ $editingArchetype ? 'Edit Archetype' : 'Add Archetype' }}</h2>
                    <p class="text-sm text-zinc-500">Global archetype details.</p>
                </div>

                <flux:input wire:model="name" label="Name" placeholder="E.g. Frost Aggro..." />
                <flux:input wire:model="format" label="Format" placeholder="E.g. Standard, Modern..." />
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
    </flux:main>
</div>