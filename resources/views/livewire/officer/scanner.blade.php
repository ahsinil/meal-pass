<?php

use App\Models\User;
use App\Models\Pickup;
use App\Models\MealSession;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\{Layout, Title, Computed};
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Layout('components.layouts.simple')]
#[Title('Pengambilan Makanan')]
class extends Component
{
    use WithPagination;
    
    public string $input = '';
    public ?int $employee_id = null;
    public string $name = '';
    public string $employee_code = '';
    public string $department = '';
    public string $phone = '';
    public ?int $overriden = 0;
    public ?string $override_reason = '';     // success/error message
    public ?int $meal_session_id = null;
    public ?int $meal_qty = null;
    public string $mode = 'manual';   // 'manual' | 'camera'

    public function mount()
    {
        $mealSessionActive = MealSession::where('is_active', 1)->first();
        $this->meal_session_id = $mealSessionActive ? $mealSessionActive->id : null;
        $this->meal_qty = $mealSessionActive ? $mealSessionActive->qty : null;
    }

    public function resetForm(): void
    {
        $this->input = '';
        $this->name = '';
        $this->employee_code = '';
        $this->department = '';
        $this->phone = '';
        $this->overriden = 0;
        $this->override_reason = '';
        $this->employee_id = null;
        $this->mode = 'manual';
        $this->meal_session_id = null;
        $this->meal_qty = null;
    }

    // manual input process
    public function checkInput(): void
    {
        $this->validate([
            'input' => ['required', 'string', 'min:9'],
        ]);

        // Check pickup code
        $pickupCode = substr(strtoupper($this->input), 0, 6);
        if ($pickupCode !== auth()->user()->pickup_code) {
            $this->dispatch('toast', type: 'error', message: 'PICKUP-404');
            $this->addError('input', 'Kode tidak valid.');
            return;
        }

        // Check if input is valid and not already picked
        $empCode = substr(strtoupper($this->input), 6);
        $employee = User::where('employee_code', $empCode)->first();
        if (!$employee) {
            $this->dispatch('toast', type: 'error', message: 'EMP-404');
            $this->addError('input', 'Kode tidak valid.');
            return;
        }
        if ($this->isEmployeePicked($employee->id)) {
            $this->dispatch('toast', type: 'info', message: 'Karyawan sudah terdata mengambil makan.');
            $this->addError('input', 'Karyawan sudah terdata mengambil makan.');
            return;
        }

        // set state value
        $this->employee_id = $employee->id;
        $this->name = $employee->name;
        $this->employee_code = $employee->employee_code;
        $this->department = $employee->department;
        $this->phone = $employee->phone;
        $this->mode = 'manual';
        $this->modal('confirm-pickup')->show();
    }

    // camera scanner detected
    public function handleDetected(string $code)
    {
        $token = trim($code);
        if ($token === '') { $this->error('Masukkan/scan kode terlebih dulu.'); return; }

        try {
            $claims = $this->verifyToken($token);
            $employee = User::find($claims['uid']);
            if (!$employee) {
                $this->dispatch('toast', type: 'error', title: 'GAGAL MEMINDAI!', message: 'EMP-404');
                return;
            }
            if ($this->isEmployeePicked($employee->id)) {
                $this->dispatch('toast', type: 'error', message: 'Karyawan sudah terdata mengambil makan.', timeout: 10000);
                return;
            }

            // set state value
            $this->employee_id = $employee->id;
            $this->name = $employee->name;
            $this->employee_code = $employee->employee_code;
            $this->department = $employee->department;
            $this->phone = $employee->phone;
            $this->mode = 'qr';
            $this->modal('confirm-pickup')->show();
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', title: 'GAGAL MEMINDAI!', message: $e->getMessage() ?: 'Invalid barcode.', timeout: 10000);
        }
    }

    private function verifyToken(string $token, int $leeway = 30): array
    {
        if (!str_contains($token, '.')) {
            // fallback: bukan token signed, mungkin kode karyawan manual
            return ['type' => 'raw', 'uid' => $token];
        }

        [$b64,$sig] = explode('.', trim($token), 2);
        $json = base64_decode($b64, true);
        if ($json === false) throw new RuntimeException('Tidak valid');

        $calc = hash_hmac('sha256', $json, config('app.key')); // HEX
        if (!hash_equals($calc, $sig)) throw new RuntimeException('Barcode tidak valid');
        $claims = json_decode($json, true);

        $now = time();
        // if (isset($claims['iat']) && $claims['iat'] > $now + $leeway) {
        //     throw new \RuntimeException('Barcode belum berlaku');
        // }
        if (isset($claims['exp']) && $claims['exp'] < $now - $leeway) {
            throw new \RuntimeException('Barcode kadaluarsa');
        }

        return $claims + ['type' => 'qr']; // tandai bahwa ini dari QR
    }

    public function b64url_encode(string $s): string {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    public function b64url_decode(string $s): string|false {
        $s = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4; if ($pad) $s .= str_repeat('=', 4 - $pad);
        return base64_decode($s, true);
    }

    private function appKeyBytes(): string {
        $k = config('app.key');
        return Str::startsWith($k, 'base64:') ? base64_decode(substr($k, 7)) : $k;
    }

    private function isEmployeePicked($id): bool
    {
        return Pickup::query()
            ->where('meal_session_id', $this->meal_session_id)
            ->where('picked_by', $id)
            ->exists();
    }

    public function savePickup(): void
    {
        $this->modal('confirm-pickup')->close();

        $data = [
            'officer_id' => auth()->user()->id,
            'meal_session_id' => $this->meal_session_id,
            'picked_by' => $this->employee_id,
            'picked_at' => now(),
            'method' => $this ?? 'manual',
        ];
        if($this->overriden != 1) {
            $data['overriden'] = 1;
            $data['override_reason'] = $this->override_reason;
        }

        Pickup::create($data);

        $this->dispatch('toast', type: 'success', message: 'Data berhasil dicatat.');
        $this->resetForm();
    }

    #[computed]
    public function pickupList()
    {
        return Pickup::query()
            ->where('meal_session_id', $this->meal_session_id)
            ->orderBy('picked_at', 'desc')
            // ->paginate(2);
            ->simplePaginate(25);
    }
};
?>

<div class="min-h-screen text-zinc-900 dark:text-gray-100">
    <livewire:partials.simple-heading />

    <!-- Two buttons navigation -->
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-6 flex items-center justify-center gap-3">
        <flux:button variant="ghost" icon="viewfinder-circle" class="px-3 py-2 font-bold" disabled>Pindai Kode</flux:button>
        <flux:button href="{{ route('pickup.history') }}" variant="primary" icon="receipt-refund" class="px-3 py-2 font-bold">Riwayat</flux:button>
    </div>

    <!-- Body -->
    <main class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

        @if ($this->meal_session_id === null)
        <section class="rounded-2xl border-1 bg-amber-50 border-amber-300 dark:bg-zinc-900/80 dark:border-zinc-700 p-6 sm:p-10">
            <flux:text class="ml-2 text-md sm:text-xl font-semibold text-center">Tidak ada makanan siap diambil</flux:text>
        </section>
        @else
        <section class="rounded-2xl border-1 bg-amber-50 border-amber-300 dark:bg-zinc-900/80 dark:border-zinc-700 p-6 sm:p-10 mb-6" x-data="{ tab: 'manual' }">
            
            <div class="flex items-center">
                <flux:icon.viewfinder-circle />
                <flux:text class="ml-2 text-md sm:text-xl font-semibold"> Pindai Kode</flux:text>
            </div>
            
            <div class="py-6 grid grid-cols-2 gap-3">
                <flux:button variant="primary" class="font-bold" @click="tab = 'manual'">Manual</flux:button>
                <flux:button variant="outline" icon="camera" class="font-bold" @click="tab = 'camera'">Kamera</flux:button>
            </div>

            <div class="py-2" x-show="tab === 'manual'">
                <flux:input id="pickup_code" label="Kode Pickup Karyawan" type="text" wire:model="input" placeholder="Scan atau ketik kode disini..." />
                <flux:button variant="primary" class="mt-3 w-full" wire:click="checkInput">Cek Kode Pickup</flux:button>
            </div>

            <div x-data="scanCamNative($wire)" x-init="init()" class="space-y-4" x-show="tab === 'camera'">
                <video x-ref="video" playsinline autoplay muted class="w-full rounded-xl bg-black aspect-video"></video>

                <div class="flex items-center gap-3 justify-end">
                    <span class="text-sm text-zinc-500" x-text="supportText"></span>
                    <flux:button variant="outline" class="px-4 py-2" @click="stop()">Stop</flux:button>
                    <flux:button variant="primary" class="px-4 py-2" @click="start()">Start</flux:button>
                </div>
            </div>
            
        </section>
        <section class="rounded-2xl border-1 bg-amber-50 border-amber-300 dark:bg-zinc-900/80 dark:border-zinc-700 p-6 sm:p-10 mb-6">
            
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <flux:icon.list-bullet class=" text-md sm:text-xl text-zinc-900 dark:text-white"/>
                    <flux:text class="ml-2 text-md sm:text-xl font-semibold">Makanan Diambil</flux:text>
                </div>
                <div class="flex items-center">
                <flux:text class="text-md sm:text-xl font-bold"><span class="text-zinc-900 dark:text-white">{{ $this->pickupList->count() }} </span> / {{ $meal_qty }}</flux:text>
                </div>
            </div>


               <div class="flow-root pt-3">
                    <ul role="list" class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @if ($this->pickupList == null)
                            <li class="py-3 sm:py-4">
                                <div class="flex items-center">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-center text-zinc-900 dark:text-white">
                                            Belum ada yang mengambil makanan
                                        </p>
                                    </div>
                                </div>
                            </li>
                        @else
                        @foreach ($this->pickupList as $data)
                        <li class="py-3 sm:py-4">
                            <div class="flex items-center">
                                <div class="flex-1 min-w-0">
                                    <p class="text-md font-medium text-zinc-900 dark:text-white">
                                        {{ $data->picker->name }}
                                    </p>
                                    <p class="text-sm text-zinc-500 truncate dark:text-zinc-400">
                                        {{-- {{ $data->picker->employee_code }} --}}
                                        {{ $data->created_at->diffForHumans() }}
                                    </p>
                                </div>
                                <div class="flex-shrink-0">
                                    <flux:badge variant="pill" color="orange">{{ ucfirst($data->method) }}</flux:badge>
                                </div>
                            </div>
                        </li>
                        @endforeach
                        @endif
                    </ul>
               </div>

               @if ($this->pickupList != null)
               <div class="mt-6">
                   {{ $this->pickupList->links() }}
                </div>
                @endif

        </section>
        @endif
    </main>

    {{-- <x-form-modal 
        name="confirm-pickup"
        title="Konfirmasi Pengambilan"
        :editing="null"
        onSubmit="processPickup"
        width="md:w-96"
        @close="resetForm"
    >

        <flux:input id="name" label="Nama Karyawan" type="text" wire:model="name" readonly />
        <flux:input id="employee_code" label="Kode Karyawan" type="text" wire:model="employee_code" readonly />
        <flux:input id="department" label="Departemen" type="text" wire:model="department" readonly />
        <flux:input id="phone" label="Nomor Whatsapp" type="text" wire:model="phone" readonly />

    </x-form-modal> --}}

    <flux:modal name="confirm-pickup" class="md:w-96" @close="resetForm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Konfirmasi Pengambilan</flux:heading>
                <flux:text class="mt-2">Pastikan data karyawan sudah benar</flux:text>
            </div>

            <flux:input id="name" label="Nama Karyawan" type="text" wire:model="name" readonly />
            <flux:input id="employee_code" label="Kode Karyawan" type="text" wire:model="employee_code" readonly />
            <flux:input id="department" label="Departemen" type="text" wire:model="department" readonly />
            <flux:input id="phone" label="Nomor Whatsapp" type="text" wire:model="phone" readonly />
            @if ($this->mode == 'manual')
            <flux:input id="override_reason" label="Alasan Override" type="text" wire:model="override_reason" placeholder="Alasan override" />
            @endif

            <div class="flex">
                <flux:spacer />
                <flux:button wire:click="savePickup" type="button" variant="primary" class="w-full" autofocus>PROSES</flux:button>
            </div>
        </div>
    </flux:modal>

</div>

<!-- Camera scanner (BarcodeDetector) -->
<script>
document.addEventListener('alpine:init', () => {
Alpine.data('scanCamNative', ($wire) => ({
    video: null, stream: null, detector: null, running: false, supportText: '',
    async init() {
        this.video = this.$refs.video;
        if (!('BarcodeDetector' in window)) {
            this.supportText = 'BarcodeDetector tidak tersedia!';
            return;
        }
        this.supportText = 'Siap scan (native).';
        this.detector = new BarcodeDetector({
            formats: ['qr_code','code_128','code_39','ean_13','ean_8','upc_e','upc_a']
        });
    },
    async start() {
        if (!('BarcodeDetector' in window)) return;
        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        this.video.srcObject = this.stream;
        await this.video.play();
        this.running = true;
        this.loop();
    },
    async stop() {
        this.running = false;
        if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
    },
    async loop() {
        if (!this.running) return;
        try {
            const codes = await this.detector.detect(this.video);
            if (codes?.length) {
                await this.stop();
                $wire.handleDetected(codes[0].rawValue || '');
                return;
            }
        } catch (e) {}
        requestAnimationFrame(() => this.loop());
    },
}));
});
</script>