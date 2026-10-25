{{-- Tombol Keluar (POST /logout). compact = hanya ikon untuk header HP. --}}
@props(['compact' => false])

<form method="POST" action="{{ route('logout') }}" {{ $attributes->class('inline-flex') }}>
    @csrf
    @if ($compact)
        <flux:button type="submit" variant="ghost" icon="arrow-right-start-on-rectangle" aria-label="Keluar" data-test="logout-top" />
    @else
        <flux:button type="submit" size="sm" icon="arrow-right-start-on-rectangle" data-test="logout-top">Keluar</flux:button>
    @endif
</form>
