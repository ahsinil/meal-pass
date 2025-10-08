<?php

use App\Models\Pickup;
use Livewire\Attributes\{Layout, Title, Computed};
use Livewire\Volt\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

new
#[Layout('components.layouts.simple')]
#[Title('Riwayat Pengambilan')]
class extends Component
{
    // listing
    public $sortField = 'created_at';
    public $sortDir   = 'desc';

    #[Computed]
    public function pickups()
    {
        $pickups = Pickup::query();
        $pickups->where('picked_by', auth()->user()->id)
                ->join('users as officer', 'officer.id', '=', 'pickups.officer_id')
                ->join('meal_sessions', 'meal_sessions.id', '=', 'pickups.meal_session_id')
                ->join('meal_windows', 'meal_windows.id', '=', 'meal_sessions.meal_window_id')
                ->select('pickups.*', 'meal_sessions.date', 'meal_windows.name as window_name', 'officer.name as officer_name');

        $pickups->orderBy($this->sortField, $this->sortDir);

        return $pickups->paginate(30);
    }

    public function sortBy(string $field) 
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = 'asc';
        }
    }

};

?>

<div class="min-h-screen text-gray-900 dark:text-gray-100">
    <livewire:partials.simple-heading />

    <!-- Two buttons navigation -->
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-6 flex items-center justify-center gap-3">
        <flux:button href="{{ route('pass') }}" variant="primary" icon="qr-code" class="px-3 py-2 font-bold">Kode QR</flux:button>
        <flux:button variant="ghost" icon="receipt-refund" class="px-3 py-2 font-bold" disabled>Riwayat</flux:button>
    </div>

    <!-- Body -->
    <main class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <section
            class="rounded-2xl border-1 bg-amber-50 border-amber-300 dark:bg-zinc-900 dark:border-gray-700 p-6 sm:p-10">
            <h1 class="text-2xl sm:text-3xl font-semibold text-center">Riwayat</h1>
            <p class="mt-2 text-center text-gray-600 dark:text-gray-400">
                Berikut adalah riwayat pengambilan makananmu.
            </p>

            <div class="mt-4 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-white text-left text-xs uppercase tracking-wide text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        <tr>
                            @php
                                $cols = ['picked_at' => 'Tanggal (Jam)', 'window_name' => 'Sesi Makan', 'officer_name' => 'Petugas'];
                            @endphp
                            @foreach ($cols as $field => $label)
                                <th class="px-4 py-3">
                                    <button wire:click="sortBy('{{ $field }}')" class="flex items-center gap-1">
                                        <span>{{ $label }}</span>
                                        @if ($sortField === $field)
                                            <svg class="h-4 w-4 {{ $sortDir === 'asc' ? 'rotate-180' : '' }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M5.23 7.21a.75.75 0 0 1 .53-.21h8.48a.75.75 0 0 1 .53 1.28l-4.24 4.24a.75.75 0 0 1-1.06 0L5.23 8.28a.75.75 0 0 1 0-1.06Z"/></svg>
                                        @endif
                                    </button>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white text-sm dark:divide-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                        @forelse ($this->pickups as $data)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-600/20">
                                <td class="px-4 py-2 font-medium">{{ $data->dateIndo }} ( {{ $data->timeIndo }} )</td>
                                <td class="px-4 py-2">{{ $data->session->mealTime ? $data->session->mealTime->name : '-' }}</td>
                                <td class="px-4 py-2">{{ $data->officer ? $data->officer->name : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-zinc-500 dark:text-zinc-400">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-2">
                {{ $this->pickups->links() }}
            </div>

        </section>
    </main>
</div>