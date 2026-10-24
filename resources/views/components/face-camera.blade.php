{{-- Kamera wajah + liveness. mode: enroll (3 sampel) atau verify (absen). --}}
@props(['mode' => 'verify', 'action' => 'submit', 'needsLocation' => false, 'samples' => 3, 'startLabel' => 'Buka kamera'])

@php($allowSkip = ! config('wokabulari.face_verification_enabled'))

<div x-data="faceCapture({ mode: @js($mode), samples: {{ (int) $samples }}, action: @js($action), needsLocation: @js((bool) $needsLocation), allowSkip: @js($allowSkip) })"
     wire:ignore {{ $attributes->class('w-full') }}>
    <div class="relative mx-auto aspect-[3/4] w-full max-w-sm overflow-hidden rounded-3xl bg-hutan ring-4"
         :class="{
            'ring-woka-100': state === 'idle' || state === 'loading',
            'ring-kunyit': state === 'challenge',
            'ring-woka-light': state === 'frontal' || state === 'sampling' || state === 'done',
            'ring-rica': state === 'error',
         }">
        <video x-ref="video" class="h-full w-full -scale-x-100 object-cover" playsinline muted></video>

        {{-- Bingkai panduan wajah --}}
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center" x-show="['challenge','frontal','sampling'].includes(state)">
            <div class="h-[62%] w-[68%] rounded-[50%] border-4 border-dashed border-white/70"></div>
        </div>

        <div class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-6 text-center text-santan" x-show="state === 'idle' || state === 'loading' || state === 'error'">
            <flux:icon.face-smile class="size-16 text-kunyit" x-show="state !== 'error'" />
            <flux:icon.exclamation-triangle class="size-14 text-rica-100" x-show="state === 'error'" />
            <p class="text-sm" x-text="message"></p>
            <flux:icon.arrow-path class="size-6 animate-spin" x-show="state === 'loading'" />
        </div>

        <div class="absolute inset-x-3 bottom-3 rounded-2xl bg-hutan/80 px-4 py-3 text-center text-sm font-semibold text-santan backdrop-blur"
             x-show="!['idle','loading','error'].includes(state)" x-text="message"></div>

        <div class="absolute inset-x-0 top-3 flex justify-center gap-1.5" x-show="mode === 'enroll' && progress > 0">
            <template x-for="i in samples" :key="i">
                <span class="size-2.5 rounded-full" :class="i <= progress ? 'bg-kunyit' : 'bg-white/40'"></span>
            </template>
        </div>
    </div>

    <div class="mx-auto mt-4 flex max-w-sm flex-col gap-2">
        <flux:button variant="primary" icon="camera" class="w-full" x-show="state === 'idle'" x-on:click="start()">{{ $startLabel }}</flux:button>
        <flux:button icon="arrow-path" class="w-full" x-show="state === 'error'" x-on:click="retry()">Coba lagi</flux:button>
        <template x-if="allowSkip && ['challenge','frontal','sampling','error'].includes(state) && mode === 'verify'">
            <flux:button variant="subtle" size="sm" class="w-full" x-on:click="skip()">Lewati verifikasi wajah (mode uji)</flux:button>
        </template>
    </div>
</div>
