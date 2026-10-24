<?php

use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

new #[Title('Log audit')] class extends Component {
    use WithPagination;

    #[Url]
    public string $log = '';

    #[Url]
    public string $search = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'activities' => Activity::query()
                ->with('causer')
                ->when($this->log, fn ($q) => $q->where('log_name', $this->log))
                ->when($this->search, fn ($q) => $q->where('description', 'like', "%{$this->search}%"))
                ->latest()
                ->paginate(30),
            'logs' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name'),
        ];
    }
}; ?>

<div>
    <x-page-header title="Log audit" subtitle="Jejak perubahan nilai, kehadiran, izin, data kesehatan, dan pengaturan." />

    <div class="mb-4 grid gap-2 sm:grid-cols-3">
        <flux:select wire:model.live="log">
            <flux:select.option value="">Semua kategori</flux:select.option>
            @foreach ($logs as $name)
                <flux:select.option :value="$name">{{ ucfirst($name) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="Cari aktivitas…" />
    </div>

    <div class="table-scroll">
        <table>
            <thead><tr><th>Waktu</th><th>Pelaku</th><th>Kategori</th><th>Aktivitas</th><th>Perubahan</th></tr></thead>
            <tbody>
                @forelse ($activities as $activity)
                    <tr wire:key="act-{{ $activity->id }}">
                        <td class="whitespace-nowrap">{{ tanggal_jam($activity->created_at) }}</td>
                        <td>{{ $activity->causer?->name ?? 'Sistem' }}</td>
                        <td><flux:badge size="sm">{{ $activity->log_name }}</flux:badge></td>
                        <td>{{ $activity->description }} @if ($activity->subject_type)<span class="text-xs text-hutan/60">({{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }})</span>@endif</td>
                        <td class="max-w-md">
                            @php($changes = $activity->attribute_changes?->isNotEmpty() ? $activity->attribute_changes : $activity->properties)
                            @if ($changes?->isNotEmpty())
                                <code class="block truncate text-xs" title="{{ json_encode($changes, JSON_UNESCAPED_UNICODE) }}">{{ \Illuminate\Support\Str::limit(json_encode($changes, JSON_UNESCAPED_UNICODE), 120) }}</code>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state title="Belum ada aktivitas" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $activities->links() }}</div>
</div>
