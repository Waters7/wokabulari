@php($user = auth()->user())
<flux:menu>
    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
        <flux:avatar :name="$user->name" :initials="$user->initials()" :src="$user->photo_url" />
        <div class="grid flex-1 text-start text-sm leading-tight">
            <flux:heading class="truncate">{{ $user->name }}</flux:heading>
            <flux:text class="truncate">{{ $user->role_label }}</flux:text>
        </div>
    </div>
    <flux:menu.separator />
    <flux:menu.radio.group>
        <flux:menu.item :href="route('profile.edit')" icon="user-circle" wire:navigate>Profil &amp; akun</flux:menu.item>
        @if (Route::has('notifications.index'))
            <flux:menu.item :href="route('notifications.index')" icon="bell" wire:navigate>Notifikasi</flux:menu.item>
        @endif
        <flux:menu.item :href="route('appearance.edit')" icon="swatch" wire:navigate>Tampilan</flux:menu.item>
    </flux:menu.radio.group>
    <flux:menu.separator />
    <form method="POST" action="{{ route('logout') }}" class="w-full">
        @csrf
        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
            Keluar
        </flux:menu.item>
    </form>
</flux:menu>
