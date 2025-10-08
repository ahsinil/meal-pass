<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\{User, MealWindow, MealSession, Pickup};

new class extends Component {
    public string $date;
    public ?int $windowId = null;

    /** @var array<int,array{id:int,label:string}> */
    public array $windows = [];

    public array $stats = [
        'prepared' => 0,
        'taken' => 0,
        'remaining' => 0,
        'remaining_yesterday' => 0,
        'active_users' => 0,
        'inactive_users' => 0,
        'coverage_pct' => 0.0,
        'overrides' => 0,
        'override_pct' => 0.0,
        'not_taken' => 0,
    ];

    public array $chart = ['labels' => [], 'series' => []];

    /** @var array<int,array{department:string,taken:int,total:int,coverage_pct:float}> */
    public array $topDepartments = [];

    /** @var array<int,array{employee:string,department:string,method:string,time:string,date:string,window:string,overriden:bool,officer:?string}> */
    public array $latestPickups = [];

    public bool $hasSession = false;

    /** @var array<string,mixed> */
    public array $activeSessionStats = [];

    /** @var array<int,array{employee:string,department:string,method:string,time:string,window:string,overriden:bool,officer:?string}> */
    public array $activeSessionLatestPickups = [];

    public bool $showActiveSessionPickups = false;

    public function mount(): void
    {
        $this->date = now('Asia/Jakarta')->toDateString();

        $this->refreshWindows();
        $this->refreshStats();
    }

    public function updatedDate()
    {
        $this->refreshWindows();
        $this->refreshStats();
    }

    public function updatedWindowId($value)
    {
        $this->windowId = $value ? (int) $value : null;
        $this->refreshStats();
    }

    protected function getSession(): ?MealSession
    {
        if (! $this->windowId) {
            return null;
        }

        return MealSession::where('date', $this->date)
            ->where('meal_window_id', $this->windowId)
            ->first();
    }

    protected function refreshStats(): void
    {
        $session   = $this->getSession();
        $this->hasSession = (bool) $session;
        $prepared  = (int) optional($session)->qty ?? 0;

        // Anggap karyawan = users dengan role 'employee'
        $activeEmp = (int) User::where('is_active', 1)
            // ->where('role', 'employee')
            ->count();

        $inactiveEmp = (int) User::where('is_active', 0)
            // ->where('role', 'employee')
            ->count();

        $taken = $session
            ? (int) Pickup::where('meal_session_id', $session->id)->count()
            : 0;

        $overrides = $session
            ? (int) Pickup::where('meal_session_id', $session->id)->where('overriden', 1)->count()
            : 0;

        $remaining    = max(0, $prepared - $taken);
        $notTaken     = max(0, $activeEmp - $taken);
        $coverage     = $activeEmp > 0 ? round(($taken / $activeEmp) * 100, 2) : 0.0;
        $overridePct  = $taken > 0 ? round(($overrides / $taken) * 100, 2) : 0.0;

        $prevRemaining = 0;
        if ($this->windowId) {
            $prevDate = Carbon::parse($this->date)->subDay()->toDateString();
            $prevSession = MealSession::where('date', $prevDate)
                ->where('meal_window_id', $this->windowId)
                ->first();

            if ($prevSession) {
                $prevPrepared = (int) ($prevSession->qty ?? 0);
                $prevTaken    = (int) Pickup::where('meal_session_id', $prevSession->id)->count();
                $prevRemaining = max(0, $prevPrepared - $prevTaken);
            }
        }

        $this->stats = [
            'prepared'        => $prepared,
            'taken'           => $taken,
            'remaining'       => $remaining,
            'remaining_yesterday' => $prevRemaining,
            'active_users'    => $activeEmp,
            'inactive_users'  => $inactiveEmp,
            'coverage_pct'    => $coverage,
            'overrides'       => $overrides,
            'override_pct'    => $overridePct,
            'not_taken'       => $notTaken,
        ];

        // chart pickups per hour
        $labels = [];
        $series = [];
        if ($session) {
            $rows = Pickup::selectRaw('HOUR(picked_at) as h, COUNT(*) as c')
                ->where('meal_session_id', $session->id)
                ->groupBy(DB::raw('HOUR(picked_at)'))
                ->orderBy('h')
                ->get();

            $labels = $rows->pluck('h')->map(fn($h) => sprintf('%02d:00', $h))->toArray();
            $series = $rows->pluck('c')->toArray();
        }
        $this->chart = ['labels' => $labels, 'series' => $series];

        $this->topDepartments = $this->buildTopDepartments($session);

        $this->latestPickups = $this->buildLatestPickups($session);

        [$this->activeSessionStats, $this->activeSessionLatestPickups] = $this->buildActiveSessionStats($session);

        if (empty($this->activeSessionStats)) {
            $this->showActiveSessionPickups = false;
        }
    }

    protected function refreshWindows(): void
    {
        $windows = MealWindow::query()
            ->where('is_active', 1)
            ->whereHas('sessions', fn ($q) => $q->where('date', $this->date))
            ->orderBy('start_time')
            ->get(['id', 'name', 'start_time', 'end_time'])
            ->map(fn ($w) => [
                'id'    => $w->id,
                'label' => "{$w->name} ({$w->start_time} - {$w->end_time})",
            ])->toArray();

        $this->windows = $windows;

        if (empty($windows)) {
            $this->windowId = null;
            return;
        }

        if ($this->windowId && ! collect($windows)->contains(fn ($w) => $w['id'] === $this->windowId)) {
            $this->windowId = null;
        }
    }

    protected function buildTopDepartments(?MealSession $session): array
    {
        // total per departemen (users aktif & role employee)
        $base = User::query()
            ->where('is_active', 1)
            // ->where('role', 'employee')
            ->select([
                'department',
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('department');

        if (!$session) {
            return $base->get()->map(function($r){
                $total = (int)$r->total;
                return [
                    'department'   => (string)$r->department ?: '-',
                    'taken'        => 0,
                    'total'        => $total,
                    'coverage_pct' => 0.0,
                ];
            })->sortByDesc('coverage_pct')->take(5)->values()->toArray();
        }

        $rows = User::query()
            ->where('users.is_active', 1)
            // ->where('users.role', 'employee')
            ->leftJoin('pickups as p', function($j) use ($session) {
                $j->on('p.picked_by', '=', 'users.id')
                  ->where('p.meal_session_id', '=', $session->id);
            })
            ->groupBy('users.department')
            ->get([
                'users.department as department',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(p.id IS NOT NULL) as taken'),
            ]);

        return $rows->map(function($r){
            $total = (int)$r->total;
            $taken = (int)$r->taken;
            $pct   = $total > 0 ? round($taken/$total*100, 2) : 0.0;
            return [
                'department'   => (string)$r->department ?: '-',
                'taken'        => $taken,
                'total'        => $total,
                'coverage_pct' => $pct,
            ];
        })->sortByDesc('coverage_pct')->take(5)->values()->toArray();
    }

    protected function buildLatestPickups(?MealSession $session): array
    {
        if (!$session) {
            return [];
        }

        return Pickup::query()
            ->with([
                'picker:id,name,department',
                'officer:id,name',
                'session.mealTime:id,name',
            ])
            ->where('meal_session_id', $session->id)
            ->orderByDesc('picked_at')
            ->limit(10)
            ->get()
            ->map(function (Pickup $pickup) {
                $pickedAt = Carbon::parse($pickup->picked_at)->timezone('Asia/Jakarta');

                return [
                    'employee'   => (string) ($pickup->picker?->name ?? 'Tidak diketahui'),
                    'department' => (string) ($pickup->picker?->department ?? '-'),
                    'method'     => (string) $pickup->method,
                    'time'       => $pickedAt->format('H:i'),
                    'date'       => $pickedAt->translatedFormat('d M Y'),
                    'window'     => (string) ($pickup->session?->mealTime?->name ?? '-'),
                    'overriden'  => (bool) $pickup->overriden,
                    'officer'    => $pickup->officer?->name,
                ];
            })
            ->toArray();
    }

    /**
     * @return array{0:array<string,mixed>,1:array<int,array{employee:string,department:string,method:string,time:string,window:string,overriden:bool,officer:?string}>}
     */
    protected function buildActiveSessionStats(?MealSession $currentSession): array
    {
        $activeSession = MealSession::query()
            ->with('mealTime:id,name,start_time,end_time')
            ->where('is_active', 1)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        if (!$activeSession) {
            return [[], []];
        }

        $prepared = (int) ($activeSession->qty ?? 0);

        if ($currentSession && $currentSession->id === $activeSession->id) {
            $taken     = $this->stats['taken'];
            $overrides = $this->stats['overrides'];
        } else {
            $taken = (int) Pickup::where('meal_session_id', $activeSession->id)->count();
            $overrides = (int) Pickup::where('meal_session_id', $activeSession->id)
                ->where('overriden', 1)
                ->count();
        }

        $remaining = max(0, $prepared - $taken);
        $progress  = $prepared > 0 ? round($taken / $prepared * 100, 1) : 0.0;

        $latest = Pickup::query()
            ->with([
                'picker:id,name,department',
                'officer:id,name',
            ])
            ->where('meal_session_id', $activeSession->id)
            ->orderByDesc('picked_at')
            ->limit(5)
            ->get()
            ->map(function (Pickup $pickup) use ($activeSession) {
                $pickedAt = Carbon::parse($pickup->picked_at)->timezone('Asia/Jakarta');

                return [
                    'employee'   => (string) ($pickup->picker?->name ?? 'Tidak diketahui'),
                    'department' => (string) ($pickup->picker?->department ?? '-'),
                    'method'     => (string) $pickup->method,
                    'time'       => $pickedAt->format('H:i'),
                    'window'     => (string) ($activeSession->mealTime?->name ?? '-'),
                    'overriden'  => (bool) $pickup->overriden,
                    'officer'    => $pickup->officer?->name,
                ];
            })
            ->toArray();

        return [[
            'id'        => $activeSession->id,
            'date'      => Carbon::parse($activeSession->date)->translatedFormat('d M Y'),
            'window'    => $activeSession->mealTime?->name ?? '-',
            'prepared'  => $prepared,
            'taken'     => $taken,
            'remaining' => $remaining,
            'overrides' => $overrides,
            'progress'  => $progress,
        ], $latest];
    }

    public function toggleActiveSessionPickups(): void
    {
        $this->showActiveSessionPickups = ! $this->showActiveSessionPickups;
    }
};

?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Dashboard Catering</flux:heading>
        <flux:text class="text-sm text-zinc-500">Tanggal Hari Ini: {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}</flux:text>
    </div>

    @can('view_active_session_stats')
    @if(!empty($activeSessionStats))
        <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/80 p-5 shadow-sm dark:border-emerald-700/50 dark:bg-emerald-900/70">
            <div class="flex flex-wrap items-start justify-between gap-6">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-300">Sesi Aktif Saat Ini</div>
                    <div class="mt-1 text-lg font-semibold text-emerald-900 dark:text-white">{{ $activeSessionStats['window'] }}</div>
                    <div class="text-sm text-emerald-700 dark:text-emerald-200/70">{{ $activeSessionStats['date'] }}</div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 flex-1 sm:flex-none">
                    <div>
                        <div class="text-xs text-emerald-600/80 dark:text-emerald-200/70">Disiapkan</div>
                        <div class="text-lg font-semibold text-emerald-900 dark:text-white">{{ $activeSessionStats['prepared'] }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-emerald-600/80 dark:text-emerald-200/70">Sudah diambil</div>
                        <div class="text-lg font-semibold text-emerald-900 dark:text-white">{{ $activeSessionStats['taken'] }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-emerald-600/80 dark:text-emerald-200/70">Sisa</div>
                        <div class="text-lg font-semibold text-emerald-900 dark:text-white">{{ $activeSessionStats['remaining'] }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-emerald-600/80 dark:text-emerald-200/70">Override</div>
                        <div class="text-lg font-semibold text-emerald-900 dark:text-white">{{ $activeSessionStats['overrides'] }}</div>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <div class="h-2 w-full rounded-full bg-emerald-200/70 dark:bg-emerald-900/50 overflow-hidden">
                    <div class="h-full bg-emerald-500 dark:bg-emerald-400" style="width: {{ min(100, $activeSessionStats['progress']) }}%"></div>
                </div>
                <div class="mt-2 text-xs text-emerald-700 dark:text-emerald-200/70">Progress pengambilan {{ $activeSessionStats['progress'] }}%</div>
            </div>
        </div>

        <div class="space-y-2">
            <button type="button" wire:click="toggleActiveSessionPickups" class="w-full inline-flex items-center justify-between rounded-2xl border border-emerald-200/70 bg-emerald-50/80 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm transition hover:bg-emerald-50 dark:border-emerald-700/50 dark:bg-emerald-900/70 dark:text-white dark:hover:bg-emerald-900/40">
                <span>Pengambilan Terbaru</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 transition-transform duration-200 {{ $showActiveSessionPickups ? 'rotate-180' : '' }}">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.12l3.71-3.89a.75.75 0 111.08 1.04l-4.24 4.45a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </button>

            @if($showActiveSessionPickups)
                <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/80 p-5 shadow-sm dark:border-emerald-700/50 dark:bg-emerald-900/70">
                    <div class="space-y-3">
                        @forelse($activeSessionLatestPickups as $pickup)
                            <div class="rounded-xl border border-emerald-200/60 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-emerald-900">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium text-emerald-900 dark:text-white">
                                            {{ $pickup['employee'] }}
                                            <span class="text-xs font-normal text-emerald-600 dark:text-zinc-200/70">({{ $pickup['department'] }})</span>
                                        </div>
                                        <div class="text-xs text-emerald-600 dark:text-zinc-200/70">{{ strtoupper($pickup['method']) }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-semibold text-emerald-900 dark:text-white">{{ $pickup['time'] }}</div>
                                        @if($pickup['officer'])
                                            <div class="text-[11px] text-emerald-600 dark:text-zinc-200/70">Petugas: {{ $pickup['officer'] }}</div>
                                        @endif
                                    </div>
                                </div>
                                @if($pickup['overriden'])
                                    <span class="mt-2 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-700 dark:bg-amber-900/40 dark:text-amber-200">Override</span>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-zinc-200/60 bg-emerald-50/40 px-4 py-6 text-center text-xs text-emerald-600 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-200/70">
                                Belum ada pengambilan untuk sesi aktif.
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Statistik Data</flux:heading>
        </div>
    @endif
    @endcan

    @can('view_all_stats')
    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
            <flux:input type="date" label="Tanggal" wire:model.live="date" />
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">Waktu Makan</label>
            <flux:select wire:model.live="windowId">
                <option value="">Pilih window</option>
                @foreach($this->windows as $opt)
                    <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <!-- KPI cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-emerald-200/60 bg-emerald-50 p-4 shadow-sm dark:border-emerald-900/40 dark:bg-emerald-900/70 dark:shadow-emerald-900/40">
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="text-zinc-500 text-sm dark:text-white">Makanan Disiapkan</div>
                    <div class="text-3xl font-semibold">{{ $stats['prepared'] }}</div>
                    <div class="text-xs text-zinc-500 dark:text-white">Sesi: {{ \Carbon\Carbon::parse($date)->translatedFormat('d-m-Y') }}</div>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-12 w-12 text-emerald-500/70">
                    <path stroke-linecap="round" stroke-width="1.5" d="M22 12H2M12 2v20M13 12a4 4 0 0 0 4 4M11 12a4 4 0 0 1-4 4"/>
                    <path stroke-width="1.5" d="M12 10.035a3.25 3.25 0 0 1 2.46-3.15c1.603-.4 3.056 1.052 2.655 2.656a3.25 3.25 0 0 1-3.15 2.46H12zM12 10.035a3.25 3.25 0 0 0-2.46-3.15c-1.603-.4-3.056 1.052-2.655 2.656a3.25 3.25 0 0 0 3.15 2.46H12z"/>
                    <path stroke-width="1.5" d="M2 12c0-4.714 0-7.071 1.464-8.536C4.93 2 7.286 2 12 2s7.071 0 8.535 1.464C22 4.93 22 7.286 22 12s0 7.071-1.465 8.535C19.072 22 16.714 22 12 22s-7.071 0-8.536-1.465C2 19.072 2 16.714 2 12Z"/>
                </svg>
            </div>
        </div>

        <div class="rounded-2xl border border-sky-200/60 bg-sky-50 p-4 shadow-sm dark:border-sky-900/40 dark:bg-sky-900/70 dark:shadow-sky-900/40">
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="text-zinc-500 text-sm dark:text-white">Sudah Diambil</div>
                    <div class="text-3xl font-semibold">{{ $stats['taken'] }}</div>
                    <div class="text-xs text-zinc-500 dark:text-white">Override: {{ $stats['overrides'] }}</div>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-12 w-12 text-sky-500/70">
                    <path stroke-width="1.5" d="M2 12c0-4.714 0-7.071 1.464-8.536C4.93 2 7.286 2 12 2s7.071 0 8.535 1.464C22 4.93 22 7.286 22 12s0 7.071-1.465 8.535C19.072 22 16.714 22 12 22s-7.071 0-8.536-1.465C2 19.072 2 16.714 2 12Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 15.8 7.143 17 10 14M6 8.8 7.143 10 10 7"/>
                    <path stroke-linecap="round" stroke-width="1.5" d="M13 9h5M13 16h5"/>
                </svg>
            </div>
        </div>

        <div class="rounded-2xl border border-red-200/60 bg-red-50 p-4 shadow-sm dark:border-red-900/40 dark:bg-red-900/70 dark:shadow-red-900/40">
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="text-zinc-500 text-sm dark:text-white">Sisa</div>
                    <div class="text-3xl font-semibold">{{ $stats['remaining'] }}</div>
                    <div class="text-xs text-zinc-500 dark:text-white">Sisa kemarin: {{ $stats['remaining_yesterday'] }}</div>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-12 w-12 text-red-500/70">
                    <path stroke-linecap="round" stroke-width="1.5" d="m15.578 3.382 2 1.05c2.151 1.129 3.227 1.693 3.825 2.708C22 8.154 22 9.417 22 11.942v.117c0 2.524 0 3.787-.597 4.801-.598 1.015-1.674 1.58-3.825 2.709l-2 1.049C13.822 21.539 12.944 22 12 22s-1.822-.46-3.578-1.382l-2-1.05c-2.151-1.129-3.227-1.693-3.825-2.708C2 15.846 2 14.583 2 12.06v-.117c0-2.525 0-3.788.597-4.802.598-1.015 1.674-1.58 3.825-2.708l2-1.05C10.178 2.461 11.056 2 12 2s1.822.46 3.578 1.382ZM21 7.5 12 12m0 0L3 7.5m9 4.5v9.5"/>
                </svg>
            </div>
        </div>

        <div class="rounded-2xl border border-purple-200/60 bg-purple-50 p-4 shadow-sm dark:border-purple-900/40 dark:bg-purple-900/70 dark:shadow-purple-900/40">
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="text-zinc-500 text-sm dark:text-white">Karyawan Aktif</div>
                    <div class="text-3xl font-semibold">{{ $stats['active_users'] }}</div>
                    <div class="flex items-center gap-1 text-xs text-zinc-500 dark:text-white">Nonaktif: {{ $stats['inactive_users'] }}</div>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-12 w-12 text-purple-500">
                    <circle cx="12" cy="6" r="4" stroke-width="1.5"/>
                    <path stroke-width="1.5" d="M20 17.5c0 2.485 0 4.5-8 4.5s-8-2.015-8-4.5S7.582 13 12 13s8 2.015 8 4.5Z"/>
                </svg>
            </div>
        </div>
    </div>
    @if($hasSession)
        <div class="rounded-2xl border border-zinc-200/60 bg-white p-4 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-900/35">
            <div class="flex items-center justify-between">
                <div class="font-medium">Pengambilan Terakhir</div>
                <div class="text-xs text-zinc-500">Sesi: {{ \Carbon\Carbon::parse($date)->translatedFormat('d M Y') }}</div>
            </div>

            <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-500">
                @forelse($latestPickups as $pickup)
                    <div class="py-3 flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $pickup['employee'] }}</div>
                                @if($pickup['overriden'])
                                    <span class="text-[10px] uppercase px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200">Override</span>
                                @endif
                            </div>
                            <div class="text-xs text-zinc-500">{{ $pickup['department'] }}</div>
                            <div class="text-[11px] text-zinc-400 mt-1">{{ $pickup['window'] }}</div>
                        </div>

                        <div class="text-right">
                            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $pickup['time'] }}</div>
                            <div class="text-xs text-zinc-500 uppercase">{{ $pickup['method'] }}</div>
                            @if($pickup['officer'])
                                <div class="text-[11px] text-zinc-400 mt-1">Petugas: {{ $pickup['officer'] }}</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-sm text-center text-zinc-500">
                        Belum ada transaksi pickup pada sesi ini.
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    @unless($hasSession)
        <div class="rounded-2xl border border-dashed border-zinc-200/60 bg-zinc-50 p-4 text-sm text-zinc-600 shadow-sm dark:border-zinc-700/50 dark:bg-amber-600/60 dark:text-white">
            Sesi untuk tanggal dan waktu makan belum dipilih! Pilih tanggal dan waktu makan agar metrik terisi.
        </div>
    @endunless
    @endcan

    {{-- <!-- Chart + Top Departments -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="xl:col-span-2 p-4 rounded-2xl border dark:border-zinc-800 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <div class="font-medium">Pickups per Hour</div>
                <div class="text-xs text-zinc-500">
                    {{ count($chart['labels']) ? 'Total '.array_sum($chart['series']).' transaksi' : 'Belum ada data' }}
                </div>
            </div>

            <canvas id="pickupsByHour" height="120"></canvas>
            <script>
            document.addEventListener('livewire:init', () => {
                const el = document.getElementById('pickupsByHour');
                if (!el) return;

                const renderChart = () => {
                    const data = {
                        labels: @js($chart['labels']),
                        datasets: [{
                            label: 'Pickups',
                            data: @js($chart['series']),
                            tension: 0.3,
                            fill: false,
                            borderWidth: 2,
                            pointRadius: 2
                        }]
                    };
                    const ctx = el.getContext('2d');
                    if (el._chart) el._chart.destroy();
                    el._chart = new Chart(ctx, {
                        type: 'line',
                        data,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                            plugins: { legend: { display: false } }
                        }
                    });
                };

                const ensureLib = () => window.Chart ? renderChart() : ( () => {
                    const s = document.createElement('script');
                    s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                    s.onload = renderChart;
                    document.head.appendChild(s);
                })();

                ensureLib();

                // Re-render after component updates
                Livewire.hook('message.processed', () => { if (window.Chart) renderChart(); });
            });
            </script>
        </div>

        <div class="p-4 rounded-2xl border dark:border-zinc-800 shadow-sm">
            <div class="font-medium mb-3">Top Departemen (Coverage)</div>
            <div class="space-y-3">
                @forelse($topDepartments as $row)
                    <div>
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-medium">{{ $row['department'] }}</div>
                            <div class="text-sm text-zinc-500">{{ $row['taken'] }}/{{ $row['total'] }}</div>
                        </div>
                        <div class="w-full h-2 bg-zinc-200 dark:bg-zinc-800 rounded-full mt-1">
                            <div class="h-2 bg-emerald-500 rounded-full" style="width: {{ $row['coverage_pct'] }}%"></div>
                        </div>
                        <div class="text-xs text-zinc-500 mt-1">{{ $row['coverage_pct'] }}%</div>
                    </div>
                @empty
                    <div class="text-sm text-zinc-500">Belum ada data</div>
                @endforelse
            </div>
        </div>
    </div> --}}

</div>
