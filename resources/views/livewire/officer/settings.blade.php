<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title};

new #[Layout('components.layouts.simple')]
#[Title('Pengaturan Akun')]
class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $phone = '';

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public bool $canUpdateProfile = false;
    public bool $canUpdatePassword = false;

    public function mount(): void
    {
        $user = Auth::user();

        $this->name  = (string) ($user->name ?? '');
        $this->email = (string) ($user->email ?? '');
        $this->phone = (string) ($user->phone ?? '');

        $this->canUpdateProfile  = $user->can('update_profile');
        $this->canUpdatePassword = $user->can('update_password');
    }

    public function updateProfile(): void
    {
        $user = Auth::user();

        if (! $this->canUpdateProfile) {
            abort(403);
        }

        $validated = $this->validate([
            'name'  => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', Rule::unique(User::class)->ignore($user->id)],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('toast', type: 'success', message: 'Profil berhasil diperbarui.');
    }

    public function updatePassword(): void
    {
        $user = Auth::user();

        if (! $this->canUpdatePassword) {
            abort(403);
        }

        try {
            $validated = $this->validate([
                'current_password'      => ['required', 'string', 'current_password'],
                'password'              => ['required', 'string', PasswordRule::defaults(), 'confirmed'],
                'password_confirmation' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');
            throw $e;
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('toast', type: 'success', message: 'Password berhasil diperbarui.');
    }
    
    public function resetPassword(): void
    {
        $this->validate([
            'phone' => ['required', 'string'],
        ]);

        Password::sendResetLink($this->only('phone'));

        $this->dispatch('toast', type: 'success', message: 'Alamat reset password berhasil dikirim, cek whatsapp kamu.');
    }
};
?>

<div class="min-h-screen text-zinc-900 dark:text-zinc-100">
    <livewire:partials.simple-heading />

    <!-- Two buttons navigation -->
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-6 flex items-center justify-center gap-3">
        <flux:button href="{{ route('pickup.scanner') }}" variant="primary" icon="viewfinder-circle" class="px-3 py-2 font-bold">Pindai Kode</flux:button>
        <flux:button href="{{ route('pickup.history') }}" variant="primary" icon="receipt-refund" class="px-3 py-2 font-bold">Riwayat</flux:button>
    </div>

    <main class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 pb-10 space-y-8">
        <section class="rounded-2xl border border-zinc-200/70 bg-amber-50 p-6 shadow-sm dark:border-zinc-900/40 dark:bg-zinc-900">
            <div class="mb-6">
                <flux:heading size="md">Tampilan</flux:heading>
                <flux:text class="text-sm text-zinc-500">Pilih tampilan yang Anda inginkan.</flux:text>
            </div>

            <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
            </flux:radio.group>
        </section>

        <section class="rounded-2xl border border-zinc-200/70 bg-amber-50 p-6 shadow-sm dark:border-zinc-900/40 dark:bg-zinc-900">
            <div class="mb-6">
                <flux:heading size="md">Profil</flux:heading>
                <flux:text class="text-sm text-zinc-500">Perbarui informasi pribadi Anda.</flux:text>
            </div>

            <form wire:submit.prevent="updateProfile" class="space-y-4">
                <flux:input wire:model.defer="name" label="Nama" type="text" required autocomplete="name" />
                <flux:input wire:model.defer="phone" label="Nomor Telepon" type="text" required autocomplete="tel" />
                <flux:input wire:model.defer="email" label="Email" type="email" autocomplete="email" />

                <div class="flex items-center justify-between gap-3">
                    @unless($canUpdateProfile)
                        <flux:text class="text-sm text-orange-600 dark:text-orange-500">Anda tidak memiliki akses untuk memperbarui profil.</flux:text>
                    @endunless
                    @if($canUpdateProfile)
                    <flux:button variant="primary" type="submit" >Simpan Profil</flux:button>
                    @else
                    <flux:button variant="outline" type="submit" disabled>Simpan Profil</flux:button>
                    @endif
                </div>
            </form>
        </section>

        @canany(['update_password', 'reset_password'])
        <section class="rounded-2xl border border-zinc-200/70 bg-amber-50 p-6 shadow-sm dark:border-zinc-900/40 dark:bg-zinc-900">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <flux:heading size="md">Password</flux:heading>
                    <flux:text class="text-sm text-zinc-500">Ganti password untuk keamanan akun.</flux:text>
                </div>

                @can('reset_password')
                <flux:button variant="primary" wire:click="resetPassword">Reset Password</flux:button>
                @endcan
            </div>

            <form wire:submit.prevent="updatePassword" class="space-y-4">
                <flux:input
                    wire:model.defer="current_password"
                    label="Password Saat Ini"
                    type="password"
                    required
                    autocomplete="current-password"
                />

                <flux:input
                    wire:model.defer="password"
                    label="Password Baru"
                    type="password"
                    required
                    autocomplete="new-password"
                />

                <flux:input
                    wire:model.defer="password_confirmation"
                    label="Konfirmasi Password"
                    type="password"
                    required
                    autocomplete="new-password"
                />

                <div class="flex items-center justify-between gap-3">
                    @unless($canUpdateProfile)
                        <flux:text class="text-sm text-orange-600 dark:text-orange-500">Anda tidak memiliki akses untuk memperbarui profil.</flux:text>
                    @endunless
                    @if($canUpdateProfile)
                    <flux:button variant="primary" type="submit" >Simpan Password</flux:button>
                    @else
                    <flux:button variant="outline" type="submit" disabled>Simpan Password</flux:button>
                    @endif
                </div>
            </form>
        </section>
        @endcanany
    </main>
</div>
