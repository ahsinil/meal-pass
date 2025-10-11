<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $phone = '';

    /**
     * Send a password reset link to the provided phone address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'phone' => ['required', 'string'],
        ]);

        Password::sendResetLink($this->only('phone'));

        session()->flash('status', __('auth.sent', ['phone' => 'No. Whatsapp']));
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('auth.reset')" :description="__('auth.reset_desc', ['phone' => 'No. Whatsapp'])" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form method="POST" wire:submit="sendPasswordResetLink" class="flex flex-col gap-6">
        <!-- phone Address -->
        <flux:input
            wire:model="phone"
            :label="__('auth.phone')"
            type="phone"
            required
            autofocus
            placeholder="08123456789"
        />

        <flux:button variant="primary" type="submit" class="w-full">{{ __('auth.send', ['phone' => 'No. Whatsapp']) }}</flux:button>
    </form>

    {{-- <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-400">
        <span>{{ __('Or, return to') }}</span>
        <flux:link :href="route('login')" wire:navigate>{{ __('log in') }}</flux:link>
    </div> --}}
</div>
