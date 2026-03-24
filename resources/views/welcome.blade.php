<x-layouts.app title="Home">
    <div class="flex h-screen w-full flex-col items-center justify-center gap-4 bg-white dark:bg-zinc-800">
        <flux:heading size="xl">TeaWizard</flux:heading>
        <flux:heading level="3">Your one in all place to store your TCG testing results</flux:subheading>
            <flux:text>Pop quiz: what does "Tea" stand for?</flux:text>
            <div class="flex gap-2">
                <flux:button variant="filled" href="/login" target="">Login</flux:button>
                <flux:button variant="filled" href="/register" target="">Register</flux:button>
            </div>
    </div>
</x-layouts.app>