@props(['position' => 'top-right','timeout' => 3500])

@php
$pos = match($position) {
  'top-left' => 'top-4 left-4',
  'bottom-right' => 'bottom-4 right-4',
  'bottom-left' => 'bottom-4 left-4',
  default => 'top-4 right-4',
};
@endphp

<div
  x-data="toast({ defaultTimeout: {{ (int) $timeout }} })"
  x-init="init()"
  x-on:toast.window="receive($event.detail)" {{-- ← bind di elemen, bukan window.addEventListener --}}
  class="pointer-events-none fixed z-[9999] {{ $pos }}"
>
  <template x-for="t in list" :key="t.id">
    <div x-show="t.show" x-transition.opacity.duration.200
         class="pointer-events-auto mb-2 w-80 overflow-hidden rounded-xl border bg-white/90 shadow-lg
                dark:border-neutral-700 dark:bg-neutral-900/90 border-neutral-200"
         @mouseenter="pause(t.id)" @mouseleave="resume(t.id)">
      <div class="flex items-start gap-3 p-3">
        <div class="mt-0.5 text-lg" x-text="icon(t.type)"></div>
        <div class="flex-1">
          <p class="text-sm font-semibold" x-text="t.title ?? titleOf(t.type)"></p>
          <p class="mt-0.5 text-sm opacity-80" x-text="t.message"></p>
        </div>
        <button class="rounded-md p-1 opacity-70 hover:opacity-100" @click="close(t.id)">×</button>
      </div>
      <div class="h-1 bg-neutral-200 dark:bg-neutral-700">
        <div class="h-full" :class="barClass(t.type)"
             :style="`width:${t.progress}%; transition:width .1s linear;`"></div>
      </div>
    </div>
  </template>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('toast', (opts = {}) => ({
    list: [],
    timers: new Map(),
    defaultTimeout: opts.defaultTimeout ?? 3500,
    _lastKeys: new Map(), // dedupe ringan

    init() {},

    // dipanggil dari x-on:toast.window
    receive(detail = {}) {
      const { message = '', type = 'success', title = null, timeout = null, key = null } = detail;
      this.push(message, type, title, timeout, key);
    },

    // dedupe: ignore jika event sama datang beruntun (<400ms) dgn key yg sama
    push(message, type='success', title=null, timeout=null, key=null) {
      const k = key ?? `${type}|${title ?? ''}|${message}`;
      const now = Date.now();
      const last = this._lastKeys.get(k) ?? 0;
      if (now - last < 400) return; // cegah dobel dekat
      this._lastKeys.set(k, now);

      const id  = crypto.getRandomValues(new Uint32Array(1))[0];
      const t   = { id, message, type, title, timeout: timeout ?? this.defaultTimeout,
                    progress: 100, show: true, paused: false, startedAt: now };
      this.list.push(t);
      this.run(id);
    },

    run(id) {
      const tick = () => {
        const t = this.list.find(x => x.id === id);
        if (!t) return;
        if (!t.paused) {
          const elapsed = Date.now() - t.startedAt;
          t.progress = Math.max(0, 100 - (elapsed / t.timeout) * 100);
          if (elapsed >= t.timeout) return this.close(id);
        }
        this.timers.set(id, requestAnimationFrame(tick));
      };
      this.timers.set(id, requestAnimationFrame(tick));
    },

    pause(id){ const t=this.list.find(x=>x.id===id); if(t) t.paused=true; },
    resume(id){ const t=this.list.find(x=>x.id===id); if(t) t.paused=false; },

    close(id) {
      const i = this.list.findIndex(x => x.id === id);
      if (i === -1) return;
      this.list[i].show = false;
      setTimeout(() => this.list.splice(i,1), 200);
      if (this.timers.has(id)) cancelAnimationFrame(this.timers.get(id));
      this.timers.delete(id);
    },

    barClass(type){
      switch(type){
        case 'error': return 'bg-red-500';
        case 'warning': return 'bg-yellow-500';
        case 'info': return 'bg-blue-500';
        default: return 'bg-emerald-500';
      }
    },
    titleOf(type){
      switch(type){
        case 'error': return 'Kesalahan';
        case 'warning': return 'Perhatian';
        case 'info': return 'Info';
        default: return 'Berhasil';
      }
    },
    icon(type){
      switch(type){
        case 'error': return '❌';
        case 'warning': return '⚠️';
        case 'info': return 'ℹ️';
        default: return '✅';
      }
    },
  }));
});
</script>