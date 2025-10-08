@pure

@php
    $padding = match ($attributes->pluck('padding')) {
        'none' => 'p-0',
        'sm' => 'p-3',
        'lg' => 'p-6',
        default => 'p-4',
    };

    $classes = Flux::classes()
        ->add('rounded-2xl border border-zinc-200/70 bg-white/70 shadow-sm backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/40')
        ->add($padding);
@endphp

<div {{ $attributes->except('padding')->class($classes) }} data-flux-card>
    {{ $slot }}
</div>
