@php
    $navigationItems = [
        ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home', 'active' => 'dashboard'],
        ['label' => 'Teams', 'route' => 'teams.index', 'icon' => 'users', 'active' => 'teams'],
        ['label' => 'Archetypes', 'route' => 'archetypes.index', 'icon' => 'command-line', 'active' => 'archetypes'],
        ['label' => 'Decks', 'route' => 'decks.index', 'icon' => 'queue-list', 'active' => 'decks'],
        ['label' => 'Results', 'route' => 'results.index', 'icon' => 'document-chart-bar', 'active' => 'results'],
        ['label' => 'Stats', 'route' => 'stats.index', 'icon' => 'chart-bar', 'active' => 'stats'],
    ];

    if (auth()->user()?->hasRole(\App\Support\Roles::ADMIN)) {
        $navigationItems[] = ['label' => 'Access Requests', 'route' => 'admin.registration-requests', 'icon' => 'shield-check', 'active' => 'admin/registration-requests'];
    }
@endphp

<flux:header container class="bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
    @auth
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        <flux:navbar class="-mb-px max-lg:hidden">
            @foreach ($navigationItems as $item)
                <flux:navbar.item :icon="$item['icon']" :href="route($item['route'])"
                    :current="request()->routeIs($item['route']) || request()->is($item['active'])">
                    {{ $item['label'] }}
                </flux:navbar.item>
            @endforeach
        </flux:navbar>
    @endauth
    <flux:spacer />
    <flux:dropdown position="top" align="start">
        <flux:profile avatar="" />
        @auth
            <flux:menu>
                <flux:menu.item readonly icon="user">Hi, {{ Auth::user()->name }}</flux:menu.item>
                <flux:menu.separator />
                <form action="/logout" method="POST">
                    @csrf
                    <flux:menu.item type="submit" icon="arrow-right-start-on-rectangle">Logout</flux:menu.item>
                </form>
            </flux:menu>
        @else
            <flux:menu>
                <flux:menu.item href="{{ route('login') }}">Login</flux:menu.item>
                <flux:menu.item href="{{ route('register') }}">Request access</flux:menu.item>
            </flux:menu>
        @endauth
    </flux:dropdown>
</flux:header>
@auth
    <flux:sidebar sticky collapsible="mobile"
        class="lg:hidden bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.header>
            <flux:sidebar.brand href="{{ route('dashboard') }}" name="TeaWizard" />
            <flux:sidebar.collapse
                class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>
        <flux:sidebar.nav>
            @foreach ($navigationItems as $item)
                <flux:sidebar.item :icon="$item['icon']" :href="route($item['route'])"
                    :current="request()->routeIs($item['route']) || request()->is($item['active'])">
                    {{ $item['label'] }}
                </flux:sidebar.item>
            @endforeach
        </flux:sidebar.nav>
    </flux:sidebar>
@endauth
