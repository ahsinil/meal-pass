<?php

use App\Models\MealSession;
use Livewire\Attributes\{Layout, Title, Computed};
use Livewire\Volt\Component;

new
#[Layout('components.layouts.simple')]
#[Title('Riwayat Pengambilan')]
class extends Component {
    // listing
    public $search    = '';
    public $activeFilter = ''; // '' (all); '1' (active); '0' (inactive)
    public $dateFilter = '';
    public $mealWindowFilter = '';
    public $sortField = 'created_at';
    public $sortDir   = 'desc';
    public $perPage   = 30;

    public function sortBy(string $field) 
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = 'asc';
        }
    }

    #[Computed]
    public function meals() {
        $q = MealSession::query();

        // date filter
        if ($this->dateFilter) {
            $q->where('date', $this->dateFilter);
        }

        // meal window filter
        if ($this->mealWindowFilter) {
            $q->where('meal_window_id', $this->mealWindowFilter);
        }

        $q->orderBy($this->sortField, $this->sortDir);
        return $q->paginate($this->perPage);
    }

}; ?>

<div class="min-h-screen text-zinc-900 dark:text-zinc-100">
    <livewire:partials.simple-heading />

    <!-- Two buttons navigation -->
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-6 flex items-center justify-center gap-3">
        <flux:button href="{{ route('pickup.scanner') }}" variant="primary" icon="viewfinder-circle" class="px-3 py-2 font-bold">Pindai Kode</flux:button>
        <flux:button variant="ghost" icon="receipt-refund" class="px-3 py-2 font-bold" disabled>Riwayat</flux:button>
    </div>

    <!-- Body -->
    <main class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <section class="rounded-2xl border-1 bg-amber-50 border-amber-300 dark:bg-zinc-900 dark:border-zinc-700 p-6 sm:p-10">
            
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <flux:icon.receipt-refund />
                    <flux:text class="ml-2 text-md sm:text-xl font-semibold">Daftar Pengambilan</flux:text>
                </div>
            </div>

            {{-- Table --}}
            <div class="mt-4 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-white text-left text-xs uppercase tracking-wide text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        <tr>
                            @php
                                $cols = ['date' => 'Tanggal', 'meal_window_id' => 'Jam Makan', 'qty' => 'Jumlah', 'picked' => 'Diambil', 'is_active' => 'Status'];
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
                        @forelse ($this->meals as $data)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-600/20">
                                <td class="px-4 py-2 font-medium">{{ $data->dateIndo }}</td>
                                <td class="px-4 py-2">{{ $data->mealTime ? $data->mealTime->name . ' (' . substr($data->mealTime->start_time, 0, 5) . ' - ' . substr($data->mealTime->end_time, 0, 5) . ')' : '-' }}</td>
                                <td class="px-4 py-2 text-zinc-500 dark:text-zinc-300">{{ $data->qty }} porsi</td>
                                <td class="px-4 py-2 text-zinc-500 dark:text-zinc-300">{{ $data->totalPickup }} porsi</td>
                                <td class="px-4 py-2 text-zinc-500 dark:text-zinc-300">
                                    {{ $data->is_active ? 'Aktif' : 'Nonaktif' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-zinc-500 dark:text-zinc-400">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-2">
                {{ $this->meals->links() }}
            </div>

        </section>
    </main>

</div>