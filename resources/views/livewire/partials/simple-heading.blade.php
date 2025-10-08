<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<div>
    <!-- Top bar -->
    <header class="border-b bg-amber-50 border-amber-300 dark:bg-zinc-900 dark:border-zinc-800/70">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <x-app-logo />

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button type="submit" icon="arrow-right-start-on-rectangle" variant="primary" class="text-sm">{{ __('Log out') }}</flux:button>
            </form>
        </div>
    </header>
</div>
