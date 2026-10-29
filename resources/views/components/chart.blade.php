{{-- Grafik Chart.js. Kunci mengikuti isi data agar grafik dibuat ulang saat filter berubah. --}}
@props(['config', 'label' => null])

@php($chartKey = 'chart-'.md5(json_encode($config)))
<div wire:ignore wire:key="{{ $chartKey }}" x-data="chart(@js($config))" {{ $attributes->class('relative') }}
     @if ($label) role="img" aria-label="{{ $label }}" @endif>
    <canvas x-ref="canvas"></canvas>
</div>
