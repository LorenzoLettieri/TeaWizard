<x-layouts.app title="Register">
    <div class="flex h-screen flex-col items-center justify-center">
        <form action="{{ route('register.store') }}" method="POST"
            class="w-full max-w-sm px-10 py-10 rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <flux:heading size="lg">Request access</flux:heading>
                <flux:text class="mt-2 mb-5">Send your request. An admin must approve it before you can log in.</flux:text>
            </div>

            <div class="space-y-6">
                @csrf
                <flux:input name="name" label="Name" type="name" placeholder="Your name" required autofocus
                    value="{{ old('name') }}" />
                @error('name')
                    <flux:error>{{ $message }}</flux:error>
                @enderror

                <flux:input name="email" label="Email" type="email" placeholder="Your email address" required autofocus
                    value="{{ old('email') }}" />
                @error('email')
                    <flux:error>{{ $message }}</flux:error>
                @enderror

                <flux:field>
                    <div class="mb-3 flex justify-between">
                        <flux:label>Password</flux:label>
                    </div>

                    <flux:input name="password" type="password" placeholder="Your password" required
                        autocomplete="current-password" />
                    @error('password')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>
                <flux:field>
                    <div class="mb-3 flex justify-between">
                        <flux:label>Confirm Password</flux:label>
                    </div>

                    <flux:input name="password_confirmation" type="password" placeholder="Repeat password" required
                        autocomplete="current-password" />
                    @error('password_confirmation')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>
            </div>

            <div class="mt-10 space-y-2">
                <flux:button type="submit" variant="primary" class="w-full">Request access</flux:button>

                <flux:button variant="ghost" class="w-full" href="{{ route('login') }}">Already approved? Log in
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts.app>
