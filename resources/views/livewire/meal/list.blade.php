<?php

use function Livewire\Volt\{title, state, rules, computed, updated, usesPagination};
use App\Models\User;
use App\Models\MealWindow;
use App\Models\MealSession;
use Illuminate\Validation\Rule;

title('Data Makanan Siap Diambil');

usesPagination();

state([
    // listing
    'search'    => '',
    'activeFilter' => '', // '' (all), '1' (active), '0' (inactive)
    'dateFilter' => '',
    'mealWindowFilter' => '',
    'sortField' => 'date',
    'sortDir'   => 'desc',
    'perPage'   => 10,

    // form
    'editing'   => false,
    'editingId' => null,
    'date'      => '',
    'meal_window_id' => '',
    'qty'       => 0,
    'notes'     => '',
    'name'      => '',
    'start_time' => '',
    'end_time'  => '',
    'location'  => '',
    'is_active' => '',

    // ui
    // 'sensitive' => false,
]);

rules(fn () => [
    'date'  => ['required','date'],
    'notes' => ['nullable','string','max:1000'],
    'meal_window_id' => ['required','exists:meal_windows,id'],
    'qty'   => ['nullable','integer','min:0'],
    'is_active' => ['required','boolean'],
    'name'  => ['required','string','max:255'],
    'start_time' => ['required','date_format:H:i'],
    'end_time'  => ['required','date_format:H:i','after:start_time'],
    'location'  => ['nullable','string','max:255'],
]);

updated([
    'search' => fn() => $this->resetPage(),
    'activeFilter' => fn() => $this->resetPage(),
    'dateFilter' => fn() => $this->resetPage(),
    'mealWindowFilter' => fn() => $this->resetPage(),
]);

$sortBy = function (string $field) {
    if ($this->sortField === $field) {
        $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortField = $field;
        $this->sortDir = 'asc';
    }
};

$resetForm = function () {
    $this->editingId = null;
    $this->date = '';
    $this->meal_window_id = '';
    $this->qty = 0;
    $this->notes = '';
    $this->is_active = '';
    $this->resetErrorBag();
    $this->editing = false;
};

$resetFormMealWindow = function () {
    $this->name = '';
    $this->start_time = '';
    $this->end_time = '';
    $this->location = '';
};

$meals = computed(function () {
    $q = MealSession::query();

    // date filter
    if ($this->dateFilter) {
        $q->where('date', $this->dateFilter);
    }

    // meal window filter
    if ($this->mealWindowFilter) {
        $q->where('meal_window_id', $this->mealWindowFilter);
    }

    // active filter
    if ($this->activeFilter !== '') {
        $q->where('is_active', $this->activeFilter);
    }

    $q->orderBy($this->sortField, $this->sortDir);
    return $q->paginate($this->perPage);
});

$mealWindows = computed(function () {
    $q = MealWindow::query();

    // active filter
    if ($this->activeFilter !== '') {
        $q->where('is_active', $this->activeFilter);
    }

    $q->orderBy('start_time', 'asc');
    
    return $q->get();
});

$createMealWindow = function () {
    $data = $this->validate([
        'name'  => ['required','string','max:255'],
        'start_time' => ['required','date_format:H:i'],
        'end_time'  => ['required','date_format:H:i','after:start_time'],
        'location'  => ['nullable','string','max:255'],
    ]);

    MealWindow::create([
        'name' => $data['name'],
        'start_time' => $data['start_time'],
        'end_time' => $data['end_time'],
        'location' => $data['location'],
        'is_active' => 1,
    ]);

    $this->modal('window-form')->close();
    $this->resetFormMealWindow();
    $this->dispatch('toast', type: 'success', message: 'Data berhasil disimpan.');
};

$edit = function (int $id) {
    $meal = MealSession::findOrFail($id);
    $this->editingId = $meal->id;
    $this->date = $meal->date;
    $this->meal_window_id = $meal->meal_window_id;
    $this->qty = $meal->qty;
    $this->notes = $meal->notes;
    $this->is_active = $meal->is_active;
    $this->resetErrorBag();
    $this->editing = true;
    $this->modal('meal-form')->show();
};

$save = function () {
    $data = $this->validate([
        'date'  => ['required','date'],
        'notes' => ['nullable','string','max:1000'],
        'meal_window_id' => ['required','exists:meal_windows,id'],
        'qty'   => ['nullable','integer','min:0'],
        'is_active' => ['required','boolean'],
    ]);

    if ($data['is_active'] == 1) {
        MealSession::where('is_active', 1)->update(['is_active' => 0]);
    }
    // siapkan payload
    $payload = [
        'date'  => $data['date'],
        'meal_window_id' => $data['meal_window_id'],
        'qty'   => $data['qty'] ?? 0,
        'notes' => $data['notes'],
        'is_active' => $data['is_active'],
    ];

    MealSession::updateOrCreate(['id' => $this->editingId], $payload);

    $this->modal('meal-form')->close();
    $this->resetForm();
    $this->dispatch('toast', type: 'success', message: 'Data berhasil disimpan.');
};

$delete = function () {
    MealSession::where('id', $this->editingId)->delete();
    $this->editingId = null;
    $this->modal('confirm-delete')->close();
    $this->resetPage();
    $this->dispatch('toast', type: 'info', message: 'User berhasil dihapus.');
};

?>

<div class="space-y-4">
    <x-page-heading title="Data Makanan" description="Kelola data makanan siap diambil." />

    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
            @can('create_meals')
            <flux:modal.trigger name="meal-form">
                <flux:button variant="primary" icon="plus" class="px-3 py-2 font-bold">Tambah Makanan</flux:button>
            </flux:modal.trigger>
            @endcan

            {{-- Filter Dropdown --}}
            <flux:dropdown>
                <flux:button icon="adjustments-horizontal">Filter</flux:button>
                <flux:menu>
                    <flux:menu.submenu heading="Tanggal">
                        <div class="px-4 py-2">
                            <input type="date" wire:model.live="dateFilter" class="w-full rounded border px-2 py-1 text-sm border-white dark:border-zinc-700" />
                        </div>
                    </flux:menu.submenu>
                    <flux:menu.submenu heading="Waktu Makan">
                        <flux:menu.radio.group wire:model.live="mealWindowFilter">
                            <flux:menu.radio value="">Semua</flux:menu.radio>
                            @foreach ($this->mealWindows as $mw)
                            <flux:menu.radio value="{{ $mw->id }}">{{ $mw->name }}</flux:menu.radio>
                            @endforeach
                        </flux:menu.radio.group>
                    </flux:menu.submenu>
                    <flux:menu.submenu heading="Status">
                        <flux:menu.radio.group wire:model.live="activeFilter">
                            <flux:menu.radio value="">Semua</flux:menu.radio>
                            <flux:menu.radio value="1">Aktif</flux:menu.radio>
                            <flux:menu.radio value="0">Nonaktif</flux:menu.radio>
                        </flux:menu.radio.group>
                    </flux:menu.submenu>
                </flux:menu>
            </flux:dropdown>
        </div>

        {{-- <div class="relative flex items-center gap-2"> --}}
            {{-- Search --}}
            {{-- <flux:input id="search" icon="magnifying-glass" type="text" wire:model.live.debounce.400ms="search" /> --}}
        {{-- </div> --}}
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                <tr>
                    @php
                        $cols = ['date' => 'Tanggal Produksi', 'meal_window_id' => 'Waktu Makan', 'qty' => 'Jumlah', 'is_active' => 'Status'];
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
                    <th class="px-4 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 bg-white text-sm dark:divide-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                @forelse ($this->meals as $data)
                    <tr class="@if($data->is_active) bg-green-100 dark:bg-green-900 @endif hover:bg-zinc-50 dark:hover:bg-zinc-600/20">
                        <td class="px-4 py-2 font-medium">{{ $data->dateIndo }}</td>
                        <td class="px-4 py-2">{{ $data->mealTime ? $data->mealTime->name . ' (' . substr($data->mealTime->start_time, 0, 5) . ' - ' . substr($data->mealTime->end_time, 0, 5) . ')' : '-' }}</td>
                        <td class="px-4 py-2 text-zinc-500 dark:text-zinc-300">{{ $data->qty }} porsi</td>
                        <td class="px-4 py-2 text-zinc-500 dark:text-zinc-300">
                            {{ $data->is_active ? 'Aktif' : 'Nonaktif' }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex flex-wrap gap-2">
                                @can('update_meals')
                                <button wire:click="edit({{ $data->id }})" class="rounded-lg border border-slate-300 px-2 py-1 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-700">Edit</button>
                                @endcan
                                @can('delete_meals')
                                <flux:modal.trigger name="confirm-delete" wire:click="$set('editingId', {{ $data->id }})">
                                <button class="rounded-lg border border-red-300 px-2 py-1 text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950/40">Hapus</button>
                                </flux:modal.trigger>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-zinc-500 dark:text-zinc-400">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>


    <div class="flex items-center gap-3 flex-row">
        <flux:select wire:model.live="perPage" class="hidden sm:block px-2 py-1 max-w-[80px]">
            @foreach([10,25,50,100] as $n)
                <option value="{{ $n }}">{{ $n }}</option>
            @endforeach
        </flux:select>
        <div class="flex-1">
            {{ $this->meals->links() }}
        </div>
    </div>

    <x-form-modal
        name="meal-form"
        title="Makanan Siap Diambil"
        :editing="$editingId"
        onSubmit="save"
        width="md:w-96"
        @close="resetForm"
        x-data="{ sensitive: true }"
    >

        <flux:input id="date" label="Tanggal Produksi*" type="date" wire:model="date" />
        <flux:input.group label="Waktu Makan*" for="meal_window_id" class="flex items-center">

            <flux:select id="meal_window_id" wire:model="meal_window_id">
                <option value="">- Pilih Waktu Makan -</option>
                @foreach ($this->mealWindows as $mw)
                    <option value="{{ $mw->id }}">{{ $mw->name }}</option>
                @endforeach
            </flux:select>
            <flux:modal.trigger name="window-form">
                <flux:button icon="plus">Waktu</flux:button>
            </flux:modal.trigger>

        </flux:input.group>
        <flux:input id="qty" label="Jumlah*" type="number" min="0" wire:model="qty" />
        <flux:select id="is_active" label="Status*" wire:model="is_active">
            <option value="" disabled>- Pilih Status -</option>
            <option value="1">Aktif</option>
            <option value="0">Nonaktif</option>
        </flux:select>
        <flux:textarea id="notes" label="Catatan" wire:model="notes" />
    
    </x-form-modal>

    <x-form-modal
        name="window-form"
        title="Waktu Makan"
        :editing="$editingId"
        onSubmit="createMealWindow"
        width="md:w-96"
        @close="resetFormMealWindow"
        x-data="{ sensitive: true }"
    >

        <flux:input id="name" label="Nama*" type="text" wire:model="name" />
        <flux:input id="start_time" label="Mulai*" type="time" wire:model="start_time" />
        <flux:input id="end_time" label="Selesai*" type="time" wire:model="end_time" />
        <flux:input id="location" label="Lokasi" type="text" wire:model="location" />
    
    </x-form-modal>

    <flux:modal name="confirm-delete" class="min-w-[22rem]" :dismissible="true" @close="$set('editingId', null)">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Data</flux:heading>
                <flux:text class="mt-2">
                    <p>Apa kamu yakin ingin menghapus data ini?</p>
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="delete" variant="danger">Delete project</flux:button>
            </div>
        </div>
    </flux:modal>

</div>