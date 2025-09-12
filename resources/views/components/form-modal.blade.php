@props([
    'name'         => 'form-modal',     // nama modal flux
    'editing'      => null,
    'title'        => 'Data',
    'description'  => null,
    'width'        => 'md:w-96',        // lebar modal (class)
    'onSubmit'     => null,             // mis: 'save' => wire:submit.prevent="save"
    'onCancel'     => '',
    'submitLabel'  => 'Simpan',
    'cancelLabel'  => 'Batal',
])

<flux:modal
    :name="$name"
    :dismissible="false"
    {{ $attributes->merge(['class' => $width]) }}
>
    <form
        @if($onSubmit) wire:submit.prevent="{{ $onSubmit }}" @endif
        class="space-y-6"
    >
        {{-- Header --}}
        @isset($header)
            {{ $header }}
        @else
            <div>
                <flux:heading size="lg">
                    {{ ($editing === null) ? 'Tambah' : 'Edit' }} {{ $title }}
                </flux:heading>
                @if($description)
                    <flux:text class="mt-2">{{ $description }}</flux:text>
                @endif
            </div>
        @endisset

        {{-- Body / Fields --}}
        {{ $slot }}

        {{-- Footer --}}
        @isset($footer)
            {{ $footer }}
        @else
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button wire:click="{{ $onCancel }}" variant="ghost">{{ $cancelLabel }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $submitLabel }}</flux:button>
            </div>
        @endisset
    </form>
</flux:modal>