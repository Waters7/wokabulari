{{-- Badge status berwarna dari PHP enum yang punya label() dan color() --}}
@props(['status', 'size' => 'sm'])

@if ($status instanceof \BackedEnum && method_exists($status, 'label'))
    <flux:badge :color="$status->color()" :size="$size" {{ $attributes }}>{{ $status->label() }}</flux:badge>
@elseif (filled($status))
    <flux:badge :size="$size" {{ $attributes }}>{{ $status }}</flux:badge>
@endif
