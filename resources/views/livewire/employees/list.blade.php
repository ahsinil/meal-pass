<?php

use function Livewire\Volt\{title, state, rules, computed, updated, usesPagination};
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

title('Data Karyawan');

usesPagination();

state([
    // listing
    'search'    => '',
    'trashFilter' => 'without', // without | with | only
    'departmentFilter' => '', // department slug or name
    'activeFilter' => '', // '' (all), '1' (active), '0' (inactive)
    'sortField' => 'created_at',
    'sortDir'   => 'desc',
    'perPage'   => 10,

    // form
    'editing' => false,
    'editingId' => null,
    'name'      => '',
    'email'     => '',
    'phone'     => '',
    'department' => '',
    'employee_code' => '',
    'is_active' => '',
    'password'  => '', // opsional (untuk create / reset)

    // ui
    'showDeleteConfirm' => false,
]);

// $__this->withQueryString([
//     'search'     => ['except' => ''],
//     'trashFilter'=> ['except' => 'without'],
//     'sortField'  => ['except' => 'created_at'],
//     'sortDir'    => ['except' => 'desc'],
//     'perPage'    => ['except' => 10],
//     'page'       => ['except' => 1],
// ]);

rules(fn () => [
    'name'  => ['required','string','max:255'],
    'email' => ['nullable','email','max:255', Rule::unique('users','email')->ignore($this->editingId)],
    'phone' => ['required','string','max:20', Rule::unique('users','phone')->ignore($this->editingId)],
    'employee_code' => ['nullable','string','max:20', Rule::unique('users','employee_code')->ignore($this->editingId)],
    'department' => ['nullable','string','max:100'],
    'is_active' => ['required','boolean'],
    // password hanya wajib saat create
    'password' => [$this->editingId ? 'nullable' : 'required','string','min:6'],
]);

updated([
    'search', function () {
        $this->resetPage();
    },
]);

// $exportUrl = computed(function () {
//     return route('users.export', [
//         'search'    => $this->search,
//         'trash'     => $this->trashFilter,
//         'sortField' => $this->sortField,
//         'sortDir'   => $this->sortDir,
//     ]);
// });

$resetForm = function () {
    $this->editingId = null;
    $this->name = '';
    $this->email = '';
    $this->phone = '';
    $this->password = '';
    $this->department = '';
    $this->employee_code = '';
    $this->is_active = '';
    $this->resetErrorBag();
    $this->editing = false;
};

$sortBy = function (string $field) {
    if ($this->sortField === $field) {
        $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortField = $field;
        $this->sortDir = 'asc';
    }
};

$users = computed(function () {
    $q = User::query();

    // trash scope
    if ($this->trashFilter === 'with') $q->withTrashed();
    elseif ($this->trashFilter === 'only') $q->onlyTrashed();

    // karyawan scope
    $q->karyawan();

    // department filter
    if ($this->departmentFilter) {
        $q->where('department', $this->departmentFilter);
    }

    // active filter
    if ($this->activeFilter !== '') {
        $q->where('is_active', $this->activeFilter);
    }

    // search
    if ($this->search) {
        $s = "%{$this->search}%";
        $q->where(fn($qq) => $qq
            ->where('name','like',$s)
            ->orWhere('email','like',$s)
            ->orWhere('phone','like',$s)
        );
    }

    // sort
    $q->orderBy($this->sortField, $this->sortDir);

    return $q->paginate($this->perPage);
});

$edit = function (int $id) {
    $u = User::withTrashed()->findOrFail($id);
    $this->editing = true;
    $this->editingId = $u->id;
    $this->name = $u->name;
    $this->email = $u->email;
    $this->phone = $u->phone;
    $this->password = ''; // kosongkan
    $this->department = $u->department;
    $this->employee_code = $u->employee_code;
    $this->is_active = $u->is_active;
    $this->modal('employee-form')->show();
};

$save = function () {
    $data = $this->validate();

    // siapkan payload
    $payload = [
        'name'  => $data['name'],
        'email' => $data['email'],
        'phone' => $data['phone'],
        'department' => $data['department'],
        'employee_code' => $data['employee_code'],
        'is_active' => $data['is_active'],
        'is_admin' => 0, // default bukan admin
    ];
    if (!empty($data['password'])) {
        $payload['password'] = Hash::make($data['password']);
    }

    User::updateOrCreate(['id' => $this->editingId], $payload);

    $this->modal('employee-form')->close();
    $this->resetForm();
    $this->dispatch('toast', type: 'success', message: 'Data berhasil disimpan.');
};

$delete = function () {
    $user = User::findOrFail($this->editingId);
    $user->is_active = 0;
    $user->save();
    $user->delete(); // soft delete
    $this->showDeleteConfirm = false;
    $this->editingId = null;
    $this->modal('confirm-delete')->close();
    $this->resetPage();
    $this->dispatch('toast', type: 'info', message: 'User berhasil dihapus.');
};

// $restore = function (int $id) {
//     User::withTrashed()->whereKey($id)->restore();
//     session()->flash('ok', 'User dipulihkan.');
// };

// $forceDelete = function (int $id) {
//     User::withTrashed()->whereKey($id)->forceDelete();
//     session()->flash('ok', 'User dihapus permanen.');
//     $this->resetPage();
// };

?>

<div class="space-y-4">
    <x-page-heading title="Data Karyawan" description="Kelola data karyawan yang dapat mengakses sistem." />

    {{-- Flash --}}
    {{-- @if (session('ok'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm dark:border-green-900 dark:bg-green-900/30 dark:text-green-200">
            {{ session('ok') }}
        </div>
    @endif --}}

    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
            <flux:modal.trigger name="employee-form">
                <flux:button variant="primary" icon="plus" class="px-3 py-2 font-bold">Tambah Karyawan</flux:button>
            </flux:modal.trigger>

            {{-- <a href="{{ $exportUrl }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                Export Excel
            </a> --}}
        </div>


        <div class="relative flex items-center gap-2">

            {{-- Filter Dropdown --}}
            <flux:dropdown>
                <flux:button icon="adjustments-horizontal">Filter</flux:button>

                <flux:menu>

                    <flux:menu.submenu heading="Department">
                        <flux:menu.radio.group wire:model.live="departmentFilter" >
                            <flux:menu.radio value="">Semua</flux:menu.radio>
                            @foreach (\App\Models\User::query()->karyawan()->select('department')->distinct()->whereNotNull('department')->pluck('department') as $dept)
                            <flux:menu.radio value="{{ $dept }}">{{ $dept }}</flux:menu.radio>
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

            {{-- Search --}}
            <flux:input id="search" icon="magnifying-glass" type="text" wire:model.live.debounce.400ms="search" />
            {{-- <input type="text" placeholder="Cari nama/email…" wire:model.live.debounce.400ms="search"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 pr-8 text-sm focus:outline-none focus:ring dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 md:w-80" />
            <svg class="pointer-events-none absolute right-2 top-2.5 h-4 w-4 opacity-60 dark:opacity-70" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.3-4.3M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg> --}}
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                <tr>
                    @php
                        $cols = ['name' => 'Nama', 'employee_code' => 'NIP', 'department' => 'Departemen', 'phone' => 'Nomor HP', 'is_active' => 'Status'];
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
                @forelse ($this->users as $u)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-600/20">
                        <td class="px-4 py-2 font-medium">{{ $u->name }}</td>
                        <td class="px-4 py-2">{{ $u->employee_code ?? '-' }}</td>
                        <td class="px-4 py-2 text-zinc-500 dark:text-zinc-300">{{ $u->department ?? '-' }}</td>
                        <td class="px-4 py-2 text-zinc-500 dark:text-zinc-300">{{ $u->phone }}</td>
                        <td class="px-4 py-2 text-zinc-500 dark:text-zinc-300">
                            {{ $u->is_active ? 'Aktif' : 'Nonaktif' }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex flex-wrap gap-2">
                                {{-- @if (!$u->deleted_at) --}}
                                    <button wire:click="edit({{ $u->id }})" class="rounded-lg border border-slate-300 px-2 py-1 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-700">Edit</button>
                                    <flux:modal.trigger name="confirm-delete" wire:click="$set('editingId', {{ $u->id }})">
                                    <button class="rounded-lg border border-red-300 px-2 py-1 text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950/40">Hapus</button>
                                    </flux:modal.trigger>
                                {{-- @else
                                    <button wire:click="restore({{ $u->id }})" class="rounded-lg border border-emerald-300 px-2 py-1 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-950/40">Restore</button>
                                    <button wire:click="forceDelete({{ $u->id }})" class="rounded-lg border border-red-300 px-2 py-1 text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950/40">Hapus Permanen</button>
                                @endif --}}
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
            {{ $this->users->links() }}
        </div>
    </div>

    {{-- <flux:modal name="employee-form" class="md:w-96" :dismissible="!$editing" @close="resetForm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Ubah' : 'Tambah' }} Karyawan</flux:heading>
                <flux:text class="mt-2">Deskripsi disini.</flux:text>
            </div>

            <flux:input id="name" label="Nama" type="text" wire:model="name" />
            <flux:input id="email" label="Email" type="email" wire:model="email" />
            <flux:input id="phone" label="Phone" type="text" wire:model="phone" />
            <flux:input id="password" label="Password" type="password" wire:model="password" :placeholder="$editingId ? 'Kosongkan jika tidak diubah' : ''" />
            
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </div>
    </flux:modal> --}}

    <x-form-modal
        name="employee-form"
        title="Karyawan"
        :editing="$editingId"
        onSubmit="save"
        width="md:w-96"
        @close="resetForm"
    >

        <flux:input id="name" label="Nama*" type="text" wire:model="name" />
        <flux:input id="phone" label="Whatsapp*" type="text" wire:model="phone" />
        <flux:input id="email" label="Email" type="email" wire:model="email" />
        <flux:input id="password" label="Password*" x-bind:type="sensitive ? 'password' : 'text'" wire:model="password" :placeholder="$editingId ? 'Kosongkan jika tidak diubah' : ''" >
            <x-slot name="iconTrailing">
                <flux:button x-on:click="sensitive = ! sensitive" type="button" size="sm" variant="subtle" icon="eye" class="-mr-1" />
            </x-slot>
        </flux:input>
        <flux:input id="employee_code" label="NIP" type="text" wire:model="employee_code" />
        <flux:input id="department" label="Departemen" type="text" wire:model="department" />
        <flux:select id="is_active" label="Status*" wire:model="is_active">
            <option value="" disabled>- Pilih Status -</option>
            <option value="1">Aktif</option>
            <option value="0">Nonaktif</option>
        </flux:select>
    
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

    {{-- Modal Delete --}}
    {{-- @if ($showDeleteConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <h3 class="mb-2 text-lg font-semibold text-slate-800 dark:text-slate-100">Hapus Data</h3>
                <p class="mb-4 text-sm text-slate-600 dark:text-slate-300">Yakin ingin menghapus data karyawan?</p>
                <div class="flex items-center justify-end gap-2">
                    <button wire:click="$set('showDeleteConfirm', false)" class="rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-600 dark:text-slate-200">Batal</button>
                    <button wire:click="delete" class="rounded-lg bg-red-600 px-3 py-2 text-white hover:bg-red-700 dark:bg-red-700">Hapus</button>
                </div>
            </div>
        </div>
    @endif --}}
</div>