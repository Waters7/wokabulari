<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tampilan')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout heading="Tampilan" subheading="Pilih mode terang, gelap, atau ikuti perangkat.">
        {{-- Tandai bahwa pengguna sudah memilih sendiri agar bawaan "terang" tidak menimpa pilihan "Perangkat". --}}
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" x-on:click="try { localStorage.setItem('wokabulari.appearance-chosen', '1') } catch (e) {}">
            <flux:radio value="light" icon="sun">Terang</flux:radio>
            <flux:radio value="dark" icon="moon">Gelap</flux:radio>
            <flux:radio value="system" icon="computer-desktop">Perangkat</flux:radio>
        </flux:radio.group>
    </x-pages::settings.layout>
</section>
