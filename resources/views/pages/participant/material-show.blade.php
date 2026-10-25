<?php

use App\Enums\MaterialType;
use App\Models\MaterialAccessLog;
use App\Models\SessionMaterial;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Materi')] class extends Component {
    public SessionMaterial $material;

    public function mount(SessionMaterial $material): void
    {
        $this->authorize('view', $material);
        $this->material = $material->load('session.classroom.language');

        if (auth()->user()->isParticipant()) {
            MaterialAccessLog::record($material, auth()->user());
        }
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <x-page-header :title="$material->title" :back="route('materials.index')"
                   :subtitle="($material->session ? tanggal($material->session->date).' · '.$material->session->topic : '')" />

    @if ($material->description)
        <p class="mb-4 text-sm">{{ $material->description }}</p>
    @endif

    <x-card :padding="false" class="overflow-hidden">
        @if ($material->type === MaterialType::Link)
            @if ($embed = $material->youtubeEmbedUrl())
                <div class="aspect-video w-full">
                    <iframe src="{{ $embed }}" class="h-full w-full" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen title="{{ $material->title }}"></iframe>
                </div>
            @else
                <div class="p-6 text-center">
                    <flux:icon.link class="mx-auto size-10 text-woka" />
                    <p class="mt-2 break-all text-sm">{{ $material->url }}</p>
                    <flux:button class="mt-3" variant="primary" icon="arrow-top-right-on-square" :href="$material->url" target="_blank" rel="noopener">Buka tautan</flux:button>
                </div>
            @endif
        @elseif ($material->type === MaterialType::Audio)
            <div class="p-6">
                <audio controls preload="metadata" class="w-full" src="{{ $material->fileUrl() }}"></audio>
            </div>
        @elseif ($material->type === MaterialType::Video)
            <video controls preload="metadata" playsinline class="w-full bg-black" src="{{ $material->fileUrl() }}"></video>
        @elseif ($material->isPdf())
            <iframe src="{{ $material->fileUrl() }}#view=FitH" class="h-[75vh] w-full" title="{{ $material->title }}"></iframe>
        @elseif ($material->isImage())
            <img src="{{ $material->fileUrl() }}" alt="{{ $material->title }}" class="w-full">
        @else
            <div class="p-6 text-center">
                <flux:icon.document-text class="mx-auto size-10 text-woka" />
                <p class="mt-2 text-sm">{{ $material->original_name }} · {{ $material->sizeLabel() }}</p>
                <p class="text-xs text-hutan/60 dark:text-santan/60">Pratinjau tidak tersedia untuk jenis berkas ini. Unduh untuk membuka.</p>
            </div>
        @endif
    </x-card>

    <div class="mt-4 flex flex-wrap gap-2">
        @if ($material->isFile() && $material->is_downloadable)
            <flux:button variant="primary" icon="arrow-down-tray" :href="$material->fileUrl(download: true)">Unduh</flux:button>
        @endif
        @if ($material->isFile() && ! $material->is_downloadable)
            <flux:text class="text-xs">Materi ini hanya bisa dilihat, tidak bisa diunduh.</flux:text>
        @endif
    </div>
</div>
