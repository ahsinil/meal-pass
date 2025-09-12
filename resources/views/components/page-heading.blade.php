<div class="relative mb-6 w-full">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ $title }}</flux:heading>
            @if (isset($description))
                <flux:subheading size="lg">{{ $description }}</flux:subheading>
            @endif
        </div>
        <div>
            {{ $slot }}
        </div>
    </div>
    <flux:separator class="mt-6" variant="subtle" />
</div>