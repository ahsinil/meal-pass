<?php

use Illuminate\Support\Str;
use Livewire\Attributes\{Layout, Title};
use Livewire\Volt\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
new
#[Layout('components.layouts.simple')]
#[Title('Kode Pengambilan')]
class extends Component
{
    public string $payload = '';
    public string $qrSvg = '';
    public int $ttlSeconds = 600; // 10 minutes
    public int $expiresAt = 0;    // unix seconds

    public function mount(): void
    {
        $this->makePayload();
        $this->makeQr();
    }

    public function b64url_encode(string $s): string {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    public function b64url_decode(string $s): string|false {
        $s = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4; if ($pad) $s .= str_repeat('=', 4 - $pad);
        return base64_decode($s, true);
    }

    public function appKeyBytes(): string {
        $k = config('app.key');
        return Str::startsWith($k, 'base64:') ? base64_decode(substr($k, 7)) : $k;
    }

    public function regenerate(): void
    {
        // If you ever want a “Refresh code” button
        $this->makePayload();
        $this->makeQr();
    }

    private function makePayload(): void
    {
        $iat = now()->timestamp;
        $exp = now()->addMinutes(10)->timestamp;

        $data = json_encode(['uid'=>auth()->user()->id,'type'=>'scan','exp'=>$exp], JSON_UNESCAPED_SLASHES);
        $sig  = hash_hmac('sha256', $data, config('app.key')); // HEX (default)
        $this->payload = base64_encode($data).'.'.$sig;

        // UX: expires in 10 minutes
        $this->expiresAt = $exp;
    }
    // private function makePayload(): void
    // {
    //     $user = auth()->user();
    //     $exp  = now()->addSeconds($this->ttlSeconds)->timestamp;

    //     $data = json_encode([
    //         'uid'  => $user->id,
    //         'type' => 'scan',
    //         'exp'  => $exp,                  // expires in 10 minutes
    //     ], JSON_UNESCAPED_SLASHES);

    //     $sig = hash_hmac('sha256', $data, config('app.key'));
    //     $this->payload = base64_encode($data).'.'.$sig;

    //     $this->expiresAt = $exp;
    // }

    private function makeQr(): void
    {
        // Inline SVG looks crisp in light/dark + easy to scale
        $this->qrSvg = QrCode::format('svg')
            ->size(260)
            ->margin(1)
            ->generate($this->payload);
    }
};

?>

<div class="min-h-screen text-zinc-900 dark:text-zinc-100">
    <livewire:partials.simple-heading />

    <!-- Two buttons navigation -->
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-6 flex items-center justify-center gap-3">
        <flux:button variant="ghost" icon="qr-code" class="px-3 py-2 font-bold" disabled>Kode QR</flux:button>
        <flux:button href="{{ route('pass.history') }}" variant="primary" icon="receipt-refund" class="px-3 py-2 font-bold">Riwayat</flux:button>
    </div>

    <!-- Body -->
    <main class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

        @if (\App\Models\MealSession::where('is_active', 1)->exists())

            <section
                class="rounded-2xl border-1 bg-amber-50 border-amber-300 dark:bg-zinc-900 dark:border-zinc-700 p-6 sm:p-10">
                <h1 class="text-2xl sm:text-3xl font-semibold text-center">Kode QR Makanan</h1>
                <p class="mt-2 text-center text-zinc-600 dark:text-zinc-400">
                    Tunjukkan ke petugas katering untuk mendapatkan makananmu hari ini.
                </p>

                <div
                    class="mt-8 max-w-[350px] sm:mt-10 rounded-2xl border-2 bg-white border-amber-300 dark:border-zinc-700 p-6 sm:p-10 mx-auto">
                    <!-- QR AREA -->
                    <div class="w-full max-w-md flex items-center justify-center">
                        {!! $this->qrSvg !!}
                    </div>
                </div>
                
                <div 
                    x-data="{
                        expiresAt: @js($expiresAt),        // unix seconds
                        leftMs: 0,
                        fmt(){
                            const s = Math.max(0, Math.ceil(this.leftMs/1000));
                            const m = String(Math.floor(s/60)).padStart(2,'0');
                            const ss = String(s%60).padStart(2,'0');
                            return m + ':' + ss;
                        },
                        tick(){
                            this.leftMs = (this.expiresAt * 1000) - Date.now();
                            if (this.leftMs <= 0) {
                                $wire.regenerate();                     // server makes new token + sets new expiresAt
                                this.expiresAt = Math.floor(Date.now()/1000) + @js($ttlSeconds);
                                this.leftMs = (this.expiresAt * 1000) - Date.now();
                            }
                        }
                    }"
                    x-init="tick(); setInterval(() => tick(), 1000)"
                    class="mt-2 text-center"
                >
                    <p class="mt-4 text-center text-sm text-zinc-500 dark:text-zinc-400">
                        Sisa waktu <span x-text="fmt()"></span>
                    </p>
                </div>
                {{-- <div class="mt-2 text-center">
                    <flux:button variant="ghost" icon="arrow-path" wire:click="regenerate">Refresh QR Code</flux:button>
                </div> --}}

                <div class="mt-6 mb-2 text-center text-xl font-medium underline">
                    {{ auth()->user()->name }}
                </div>

                {{-- <div class="mb-2 text-center text-zinc-600 dark:text-zinc-400">
                    {{ auth()->user()->employee_code ?? '' }} &bull; {{ auth()->user()->phone ?? 'No Phone' }}
                </div> --}}

                <div class="mb-2 text-center text-zinc-600 dark:text-zinc-400">
                    {{ auth()->user()->department ?? '' }} &bull; {{ auth()->user()->pickup_code ?? '' }}{{ auth()->user()->employee_code ?? '' }}
                </div>
            
            </section>

        @else
            
            <section
                class="rounded-2xl border-1 bg-amber-50 border-amber-300 dark:bg-zinc-900 dark:border-zinc-700 p-6 sm:p-10">
                <h1 class="text-2xl sm:text-3xl font-semibold text-center">Duh, Maafkan Kami</h1>
                <p class="mt-2 text-center text-zinc-600 dark:text-zinc-400">
                    Tidak ada makanan yang siap diambil. Hubungi petugas katering apabila seharusnya ada makanan hari ini.
                </p>
            </section>

        @endif

    </main>
</div>