<x-layouts.app title="Login">
    <div class="flex h-screen flex-col items-center justify-center">
        <form action="{{ route('login') }}" method="POST"
            class="w-full max-w-sm px-10 py-10 rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <flux:heading size="lg">Log in</flux:heading>
                <flux:text class="mt-2 mb-5">Welcome back!</flux:text>
            </div>

            @if (session('status'))
                <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-200">
                    {{ session('status') }}
                </div>
            @endif

            <div class="space-y-6">
                @csrf
                <flux:input name="email" label="Email" type="email" placeholder="Your email address" required autofocus
                    value="{{ old('email') }}" />

                <flux:field>
                    <div class="mb-3 flex justify-between">
                        <flux:label>Password</flux:label>

                        <flux:link href="{{ route('password.request') }}" variant="subtle" class="text-sm">Forgot
                            password?</flux:link>
                    </div>

                    <flux:input name="password" type="password" placeholder="Your password" required
                        autocomplete="current-password" />

                    @error('email')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>
            </div>

            <div class="mt-10 space-y-2">
                <flux:button type="submit" variant="primary" class="w-full">Log in</flux:button>

                <flux:button variant="ghost" class="w-full" href="{{ route('register') }}">Request a new account
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts.app>
