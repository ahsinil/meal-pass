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

            <div class="flex items-center gap-3">
                @role('staff')
                <flux:button href="{{ route('officer.settings') }}" icon="cog-6-tooth" variant="outline" class="text-sm" wire:navigate>Pengaturan</flux:button>
                @endrole
                @role('employee')
                <flux:button href="{{ route('employee.settings') }}" icon="cog-6-tooth" variant="outline" class="text-sm" wire:navigate>Pengaturan</flux:button>
                @endrole

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <flux:button type="submit" icon="arrow-right-start-on-rectangle" variant="primary" class="text-sm">{{ __('auth.logout') }}</flux:button>
                </form>
            </div>
        </div>
    </header>
</div>
