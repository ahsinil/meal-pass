<?php

use function Livewire\Volt\{title, state, rules, computed, updated, usesPagination};
use App\Models\User;
use App\Models\Pickup;
use App\Models\MealWindow;
use App\Models\MealSession;
use App\Exports\PickupExport;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

title('Riwayat Pengambilan');

usesPagination();

state([
    // listing
    'search'    => '',
    'departmentFilter' => '',
    'methodFilter' => '',
    'overridenFilter' => '', // '' (all), '1' (true), '0' (false)
    'sortField' => 'picked_at',
    'sortDir'   => 'desc',
    'perPage'   => 10,
    'start_date_filter' => '',
    'end_date_filter' => '',

    // form
    'editing'   => false,
    'editingId' => null,
    'start_date' => '',
    'end_date' => '',
    'picked_by' => '',
    'picked_at' => '',
    'employee_name' => '',
    'selected_employee' => '',
    'mealSessions' => [],
    'meal_session_id' => '',
    'overriden_reason' => '',
    'query' => '',
]);

rules(fn () => [
    'start_date'  => ['required','date'],
    'end_date'  => ['required','date','after:start_date'],
]);

updated([
    'search' => fn () => $this->resetPage(),
    'start_date_filter' => function () {
        if ($this->end_date_filter == '') {
            $this->end_date_filter = $this->start_date_filter;
        }
        $this->resetPage();
    },
    'end_date_filter' => fn () => $this->resetPage(),
    'departmentFilter' => fn() => $this->resetPage(),
    'methodFilter' => fn() => $this->resetPage(),
    'overridenFilter' => fn() => $this->resetPage(),
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
    $this->editing = false;
    $this->editingId = null;
    $this->start_date = '';
    $this->end_date = '';
    $this->picked_by = '';
    $this->picked_at = '';
    $this->employee_name = '';
    $this->selected_employee = '';
    $this->mealSessions = [];
    $this->meal_session_id = '';
    $this->overriden_reason = '';
    $this->query = '';
    $this->resetErrorBag();
    $this->editing = false;
};

$resetDateFilter = function () {
    $this->start_date_filter = '';
    $this->end_date_filter = '';
};

$pickups = computed(function () {
    $q = Pickup::query();

    $q->join('users as officer', 'officer.id', '=', 'pickups.officer_id')
    ->join('users as employee', 'employee.id', '=', 'pickups.picked_by')
    ->join('meal_sessions', 'meal_sessions.id', '=', 'pickups.meal_session_id')
    ->join('meal_windows', 'meal_windows.id', '=', 'meal_sessions.meal_window_id')
    ->select('pickups.*', 'employee.name as employee_name', 'employee.department as department', 'meal_windows.name as window_name', 'officer.name as officer_name');

    // // date filter
    // if ($this->dateFilter) {
    //     $q->where('date', $this->dateFilter);
    // }
    
    // department filter
    if ($this->departmentFilter) {
        $q->where('employee.department', $this->departmentFilter);
    }

    // method filter
    if ($this->methodFilter) {
        $q->where('method', $this->methodFilter);
    }

    // overriden filter
    if ($this->overridenFilter) {
        $q->where('overriden', $this->overridenFilter);
    }

    // date filter
    if ($this->start_date_filter) {
        $start = Carbon::parse($this->start_date_filter)->startOfDay();          // 00:00 WIB -> UTC
        $end   = Carbon::parse($this->end_date_filter ?: $this->start_date_filter)
                        ->addDay()->startOfDay();                                       // < besok 00:00 WIB -> UTC

        $q->where('pickups.picked_at', '>=', $start)
        ->where('pickups.picked_at', '<',  $end);
    }

    if ($this->search) {
        $s = "%{$this->search}%";
        $q->where(fn($qq) => $qq
            ->where('employee.name','like',$s)
            ->orWhere('employee.department','like',$s)
            ->orWhere('officer.name','like',$s)
        );
    }

    if($this->sortField == 'picked_time') {
        $q->orderByRaw('( (HOUR(picked_at) - HOUR(NOW()) + 24) % 24 ) ' . $this->sortDir);
    } else {
        $q->orderBy($this->sortField, $this->sortDir);
    }
    return $q->paginate($this->perPage);
});

$departmentList = computed(function (): array {
    return User::select('department')->distinct()->pluck('department')->toArray();
});

$methodList = computed(function (): array {
    return Pickup::select('method')->distinct()->pluck('method')->toArray();
});

$edit = function (int $id) {
    $pickup = Pickup::findOrFail($id);
    $this->editingId = $pickup->id;
    $this->picked_by = $pickup->picked_by;
    $this->picked_at = $pickup->date_indo . ' - Pukul ' . $pickup->time_indo;
    $this->meal_session_id = $pickup->meal_session_id;
    $this->overriden_reason = $pickup->overriden_reason;
    $this->employee_name = $pickup->picker->name;

    $this->mealSessions = MealSession::where('date', '=', \Carbon\Carbon::parse($pickup->picked_at)->format('Y-m-d'))->get();

    $this->resetErrorBag();
    $this->editing = true;
    $this->modal('edit-form')->show();
};

$employeeList = computed(function (): array {
    return User::where('is_active', 1)
        ->where('is_admin', 0)
        ->get(['id', 'name'])
        ->toArray();
});

$getFilteredEmployeeProperty = function () {
    $q = str($this->query)->lower();

    return collect($this->employeeList)        // ini array dari computed()
        ->filter(fn ($row) => str($row['name'])->lower()->contains($q))
        ->pluck('name', 'id');  
};

$selectEmployee = function ($id, $name) {
    $this->selected_employee = $id;
    $this->query = $name;
};

$save = function () {
    $data = $this->validate([
        'meal_session_id' => ['required', 'exists:meal_sessions,id'],
        'overriden_reason' => ['required','string','min:5'],
    ]);

    // siapkan payload
    $payload = [
        'meal_session_id' => $data['meal_session_id'],
        'overriden' => 1,
        'overriden_reason' => $data['overriden_reason'],
    ];
    if ($this->selected_employee) {
        $this->validate([
            'selected_employee' => ['required', 'exists:users,id'],
        ]);
        $payload['picked_by'] = $this->selected_employee;
    }

    Pickup::find($this->editingId)->update($payload);

    $this->modal('edit-form')->close();
    $this->resetForm();
    $this->dispatch('toast', type: 'success', message: 'Data berhasil diubah.');
};

$delete = function () {
    Pickup::where('id', $this->editingId)->delete();
    $this->editingId = null;
    $this->modal('confirm-delete')->close();
    $this->resetPage();
    $this->dispatch('toast', type: 'info', message: 'User berhasil dihapus.');
};

$export = function () {
    $this->validate([
        'start_date' => ['required'],
        'end_date' => ['required'],
    ]);
    $this->modal('export-form')->close();

    return new PickupExport($this->start_date, $this->end_date);
};

$exportToday = function () {
    return (new PickupExport())->today();
};

$exportYesterday = function () {
    return (new PickupExport())->yesterday();
};

$exportThisMonth = function () {
    return (new PickupExport())->thisMonth();
}

?>

<div class="space-y-4">
    <x-page-heading title="Data Pengambilan" description="Kelola data pengambilan makanan." />

    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
            {{-- <flux:modal.trigger name="export-form">
                <flux:button variant="outline" icon="document-arrow-down" class="px-3 py-2 font-bold">Export Excel</flux:button>
            </flux:modal.trigger> --}}
            <flux:dropdown>
                <flux:button variant="outline" icon="document-arrow-down">Export Excel</flux:button>
                <flux:menu>
                    <flux:modal.trigger name="export-form">
                        <flux:menu.item icon="calendar">Pilih Tanggal</flux:menu.item>
                    </flux:modal.trigger>
                    
                    <flux:menu.separator />
                    
                    <flux:menu.item wire:click='exportToday'>Hari Ini</flux:menu.item>
                    <flux:menu.item wire:click='exportYesterday'>Kemarin</flux:menu.item>
                    <flux:menu.item wire:click='exportThisMonth'>Bulan Ini</flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            {{-- Filter Dropdown --}}
            <flux:dropdown>
                <flux:button variant="outline" icon="adjustments-horizontal">Filter</flux:button>
                <flux:menu>
                    <flux:modal.trigger name="date-filter">
                    <flux:menu.item icon="calendar">Tanggal</flux:menu.item>
                    </flux:modal.trigger>

                    <flux:menu.separator />

                    <flux:menu.submenu heading="Bagian">
                        <flux:menu.radio.group wire:model.live="departmentFilter">
                            <flux:menu.radio value="">Semua</flux:menu.radio>
                            @foreach ($this->departmentList as $data)
                            @if ($data == '') @continue @endif
                            <flux:menu.radio value="{{ $data }}">{{ ucfirst($data) }}</flux:menu.radio>
                            @endforeach
                        </flux:menu.radio.group>
                    </flux:menu.submenu>
                    <flux:menu.submenu heading="Metode">
                        <flux:menu.radio.group wire:model.live="methodFilter">
                            <flux:menu.radio value="">Semua</flux:menu.radio>
                            @foreach ($this->methodList as $data)
                            @if ($data == '') @continue @endif
                            <flux:menu.radio value="{{ $data }}">{{ ($data == 'qr') ? 'QR' : ucfirst($data) }}</flux:menu.radio>
                            @endforeach
                        </flux:menu.radio.group>
                    </flux:menu.submenu>
                    {{-- <flux:menu.submenu heading="Ambil Alih">
                        <flux:menu.radio.group wire:model.live="overridenFilter">
                            <flux:menu.radio value="">Semua</flux:menu.radio>
                            <flux:menu.radio value="1">Ya</flux:menu.radio>
                            <flux:menu.radio value="0">Tidak</flux:menu.radio>
                        </flux:menu.radio.group>
                    </flux:menu.submenu> --}}
                </flux:menu>
            </flux:dropdown>
        </div>


        <div class="relative flex items-center gap-2">
            {{-- Search --}}
            <flux:input id="search" icon="magnifying-glass" type="text" wire:model.live.debounce.400ms="search" />
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                <tr>
                    @php
                        $cols = ['picked_at' => 'Tanggal Pengambilan', 'picked_time' => 'Jam', 'employee_name' => 'Karyawan', 'department' => 'Bagian', 'window_name' => 'Sesi Makan', 'officer_name' => 'Petugas', 'method' => 'Metode'];
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
                @forelse ($this->pickups as $data)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-600/20">
                        <td class="px-4 py-2">{{ $data->dateIndo }}</td>
                        <td class="px-4 py-2">{{ $data->timeIndo }}</td>
                        <td class="px-4 py-2 font-medium">{{ $data->employee_name ? $data->employee_name : '-' }}</td>
                        <td class="px-4 py-2">{{ $data->department ? $data->department : '-' }}</td>
                        <td class="px-4 py-2">{{ ucfirst($data->window_name) }}</td>
                        <td class="px-4 py-2">{{ $data->officer ? $data->officer->name : '-' }}</td>
                        <td class="px-4 py-2">{{ ($data->method == 'qr') ? 'QR' : ucfirst($data->method) }}</td>
                        {{-- <td class="px-4 py-2 text-zinc-500 dark:text-zinc-300">
                            {{ $data->overriden ? 'Ya' : 'Tidak' }}
                        </td> --}}
                        <td class="px-4 py-2">
                            <div class="flex flex-wrap gap-2">
                                <button wire:click="edit({{ $data->id }})" class="rounded-lg border border-slate-300 px-2 py-1 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-700">Edit</button>
                                <flux:modal.trigger name="confirm-delete" wire:click="$set('editingId', {{ $data->id }})">
                                <button class="rounded-lg border border-red-300 px-2 py-1 text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950/40">Hapus</button>
                                </flux:modal.trigger>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-6 text-center text-zinc-500 dark:text-zinc-400">Tidak ada data.</td></tr>
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
            {{ $this->pickups->links() }}
        </div>
    </div>

    <flux:modal name="date-filter" class="min-w-[22rem]" :dismissible="true">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Filter Tanggal</flux:heading>
            </div>

            <flux:input id="start_date" label="Tanggal Awal*" type="date" max="{{ now()->format('Y-m-d') }}" wire:model.live="start_date_filter" />
            <flux:input id="end_date" label="Tanggal Akhir" type="date" min="{{ $this->start_date_filter }}" max="{{ now()->format('Y-m-d') }}" wire:model.live="end_date_filter" />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button wire:click="resetDateFilter" variant="outline">Reset</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="export-form" class="min-w-[22rem]" :dismissible="true">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Export Data</flux:heading>
            </div>

            <flux:input id="start_date" label="Tanggal Awal*" type="date" max="{{ now()->format('Y-m-d') }}" wire:model.live="start_date" />
            <flux:input id="end_date" label="Tanggal Akhir" type="date" min="{{ $this->start_date }}" max="{{ now()->format('Y-m-d') }}" wire:model="end_date" />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button wire:click="export" variant="primary">Export</flux:button>
            </div>
        </div>
    </flux:modal>

    <x-form-modal
        name="edit-form"
        title="Data Pengambilan"
        :editing="$editingId"
        onSubmit="save"
        width="md:w-96"
        @close="resetForm"
    >

        <flux:input id="date" type="text" value="{{ $this->picked_at }}" readonly />
        <flux:select id="meal_session_id" label="Waktu Makan*" wire:model.live="meal_session_id">
            <option value="">- Pilih Waktu Makan -</option>
            @foreach ($mealSessions as $data)
                <option value="{{ $data->id }}">{{ $data->dateIndo }} - {{ $data->mealTime->name }}</option>
            @endforeach
        </flux:select>
        <div 
            x-data="{ open: false, karyawan: $wire.entangle('selected_employee') }" 
            class="relative"
        >
            @if ($this->filteredEmployee != [])
            <flux:input 
                wire:model.live.debounce.700ms="query" 
                :label="__('Karyawan*')" 
                placeholder="{{ $employee_name }}"
                @click="open = true;"
            />
            <div 
                class="absolute z-50 w-full mt-1 bg-white border rounded-lg shadow dark:bg-zinc-900"
                x-init="$watch('karyawan', value => open = false)" 
                x-show="open" 
                @click.outside="$el.previousElementSibling.contains($event.target) ? null : open = false" 
            >
                <ul class="max-h-60 overflow-y-auto">
                    @forelse($this->filteredEmployee as $key => $option)
                    <li 
                        class="cursor-pointer hover:bg-gray-100 dark:hover:bg-zinc-700 px-3 py-2" 
                        wire:click="selectEmployee('{{ $key }}', '{{ $option }}')"
                    >
                        {{ $option }}
                    </li>
                    @empty
                    <li class="px-3 py-2 text-zinc-500">Ketik untuk mencari...</li>
                    @endforelse
                </ul>
            </div>
            @elseif ($this->filteredEmployee == null)
            <flux:input :label="__('Karyawan*')" placeholder="Tidak dapat mendapatkan data karyawan" disabled />
            @endif
        </div>
        <flux:textarea id="overriden_reason" label="Alasan Penggantian*" wire:model="overriden_reason" />
    
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