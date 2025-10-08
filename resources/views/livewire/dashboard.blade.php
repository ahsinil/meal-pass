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
    public function updatedWindowId() { $this->refreshStats(); }

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
            $prevSession = MealSession::where('date', $prevDate)->select('id')->distinct()->pluck('id');
            $prevQty = MealSession::where('date', $prevDate)->sum('qty');

            if ($prevSession) {
                $prevPrepared = (int) $prevQty ?? 0;
                $prevTaken    = (int) Pickup::whereIn('meal_session_id', $prevSession)->count();
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

        if (! $this->windowId || ! collect($windows)->contains(fn ($w) => $w['id'] === $this->windowId)) {
            $this->windowId = $windows[0]['id'];
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
};

?>

<!-- VIEW: Tailwind + Flux UI -->
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="lg">Dashboard Catering</flux:heading>
        <flux:text class="text-sm text-zinc-500">Tanggal Hari Ini: {{ \Carbon\Carbon::now()->translatedFormat('d MY') }}</flux:text>
    </div>

    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
            <flux:input type="date" label="Tanggal" wire:model.live="date" />
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">Waktu Makan</label>
            <flux:select wire:model.live="windowId">
                @foreach($this->windows as $opt)
                    <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <!-- KPI cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-emerald-200/60 bg-emerald-50 p-4 shadow-sm dark:border-emerald-900/40 dark:bg-emerald-900/25">
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="text-zinc-500 text-sm">Makanan Disiapkan</div>
                    <div class="text-3xl font-semibold">{{ $stats['prepared'] }}</div>
                    <div class="text-xs text-zinc-500">Sesi: {{ \Carbon\Carbon::parse($date)->translatedFormat('d-m-Y') }}</div>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-12 w-12 text-emerald-500/70">
                    <path stroke-linecap="round" stroke-width="1.5" d="M22 12H2M12 2v20M13 12a4 4 0 0 0 4 4M11 12a4 4 0 0 1-4 4"/>
                    <path stroke-width="1.5" d="M12 10.035a3.25 3.25 0 0 1 2.46-3.15c1.603-.4 3.056 1.052 2.655 2.656a3.25 3.25 0 0 1-3.15 2.46H12zM12 10.035a3.25 3.25 0 0 0-2.46-3.15c-1.603-.4-3.056 1.052-2.655 2.656a3.25 3.25 0 0 0 3.15 2.46H12z"/>
                    <path stroke-width="1.5" d="M2 12c0-4.714 0-7.071 1.464-8.536C4.93 2 7.286 2 12 2s7.071 0 8.535 1.464C22 4.93 22 7.286 22 12s0 7.071-1.465 8.535C19.072 22 16.714 22 12 22s-7.071 0-8.536-1.465C2 19.072 2 16.714 2 12Z"/>
                </svg>
            </div>
        </div>

        <div class="rounded-2xl border border-sky-200/60 bg-sky-50 p-4 shadow-sm dark:border-sky-900/40 dark:bg-sky-900/25">
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="text-zinc-500 text-sm">Sudah Diambil</div>
                    <div class="text-3xl font-semibold">{{ $stats['taken'] }}</div>
                    <div class="text-xs text-zinc-500">Override: {{ $stats['overrides'] }}</div>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-12 w-12 text-sky-500/70">
                    <path stroke-width="1.5" d="M2 12c0-4.714 0-7.071 1.464-8.536C4.93 2 7.286 2 12 2s7.071 0 8.535 1.464C22 4.93 22 7.286 22 12s0 7.071-1.465 8.535C19.072 22 16.714 22 12 22s-7.071 0-8.536-1.465C2 19.072 2 16.714 2 12Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 15.8 7.143 17 10 14M6 8.8 7.143 10 10 7"/>
                    <path stroke-linecap="round" stroke-width="1.5" d="M13 9h5M13 16h5"/>
                </svg>
            </div>
        </div>

        <div class="rounded-2xl border border-amber-200/60 bg-amber-50 p-4 shadow-sm dark:border-amber-900/40 dark:bg-amber-900/25">
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="text-zinc-500 text-sm">Sisa</div>
                    <div class="text-3xl font-semibold">{{ $stats['remaining'] }}</div>
                    <div class="text-xs text-zinc-500">Sisa kemarin: {{ $stats['remaining_yesterday'] }}</div>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-12 w-12 text-amber-500/70">
                    <path stroke-linecap="round" stroke-width="1.5" d="m15.578 3.382 2 1.05c2.151 1.129 3.227 1.693 3.825 2.708C22 8.154 22 9.417 22 11.942v.117c0 2.524 0 3.787-.597 4.801-.598 1.015-1.674 1.58-3.825 2.709l-2 1.049C13.822 21.539 12.944 22 12 22s-1.822-.46-3.578-1.382l-2-1.05c-2.151-1.129-3.227-1.693-3.825-2.708C2 15.846 2 14.583 2 12.06v-.117c0-2.525 0-3.788.597-4.802.598-1.015 1.674-1.58 3.825-2.708l2-1.05C10.178 2.461 11.056 2 12 2s1.822.46 3.578 1.382ZM21 7.5 12 12m0 0L3 7.5m9 4.5v9.5"/>
                </svg>
            </div>
        </div>

        <div class="rounded-2xl border border-purple-200/60 bg-purple-50 p-4 shadow-sm dark:border-purple-900/40 dark:bg-purple-900/25">
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="text-zinc-500 text-sm">Karyawan Aktif</div>
                    <div class="text-3xl font-semibold">{{ $stats['active_users'] }}</div>
                    <div class="flex items-center gap-1 text-xs text-zinc-500">Nonaktif: {{ $stats['inactive_users'] }}</div>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-12 w-12 text-purple-500/70">
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

    @unless($hasSession)
        <div class="rounded-2xl border border-dashed border-zinc-200/60 bg-zinc-50 p-4 text-sm text-zinc-600 shadow-sm dark:border-zinc-700/50 dark:bg-zinc-900/35 dark:text-zinc-400">
            Sesi untuk tanggal dan window terpilih belum dibuat. Pilih tanggal dan window agar metrik terisi.
        </div>
    @endunless
</div>
