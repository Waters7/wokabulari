<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Notifikasi')] class extends Component {
    use WithPagination;

    public bool $unreadOnly = false;

    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        if ($url = $notification->data['url'] ?? null) {
            $this->redirect($url, navigate: true);
        }
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function with(): array
    {
        return [
            'notifications' => auth()->user()->notifications()
                ->when($this->unreadOnly, fn ($q) => $q->whereNull('read_at'))
                ->latest()
                ->paginate(20),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Notifikasi">
        <x-slot:actions>
            <flux:switch wire:model.live="unreadOnly" label="Belum dibaca saja" />
            <flux:button size="sm" icon="check" wire:click="markAllRead">Tandai semua dibaca</flux:button>
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <ul class="divide-y divide-woka-100 dark:divide-hutan-800">
            @forelse ($notifications as $notification)
                @php($data = $notification->data)
                @php($level = $data['level'] ?? 'info')
                <li wire:key="n-{{ $notification->id }}">
                    <button type="button" wire:click="open('{{ $notification->id }}')"
                            @class(['flex w-full items-start gap-3 px-4 py-3 text-start hover:bg-woka-50 dark:hover:bg-hutan-800', 'bg-kunyit-50/60 dark:bg-hutan-800/60' => ! $notification->read_at])>
                        <span @class([
                            'mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full',
                            'bg-woka-50 text-woka' => in_array($level, ['info', 'success']),
                            'bg-kunyit-50 text-kunyit-700' => $level === 'warning',
                            'bg-rica-50 text-rica' => $level === 'danger',
                        ])>
                            <flux:icon :name="$data['icon'] ?? 'bell'" class="size-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold">{{ $data['title'] ?? 'Notifikasi' }}</span>
                            <span class="block text-sm text-hutan/80 dark:text-santan/80">{{ $data['body'] ?? '' }}</span>
                            <span class="mt-0.5 block text-xs text-hutan/50 dark:text-santan/50">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                        @unless ($notification->read_at)
                            <span class="mt-2 size-2.5 shrink-0 rounded-full bg-rica" aria-label="Belum dibaca"></span>
                        @endunless
                    </button>
                </li>
            @empty
                <li><x-empty-state icon="bell" title="Belum ada notifikasi" class="m-4" /></li>
            @endforelse
        </ul>
    </x-card>
    <div class="mt-4">{{ $notifications->links() }}</div>
</div>
