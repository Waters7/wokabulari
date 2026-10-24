<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="Pengaturan akun">
            <flux:navlist.item icon="user-circle" :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate>Profil</flux:navlist.item>
            <flux:navlist.item icon="lock-closed" :href="route('security.edit')" :current="request()->routeIs('security.edit')" wire:navigate>Kata sandi</flux:navlist.item>
            <flux:navlist.item icon="swatch" :href="route('appearance.edit')" :current="request()->routeIs('appearance.edit')" wire:navigate>Tampilan</flux:navlist.item>
            @if (auth()->user()->isParticipant() && Route::has('face.enroll'))
                <flux:navlist.item icon="face-smile" :href="route('face.enroll')" :current="request()->routeIs('face.enroll')" wire:navigate>Wajah referensi</flux:navlist.item>
            @endif
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <h2 class="font-display text-xl font-bold text-hutan dark:text-santan">{{ $heading ?? '' }}</h2>
        <p class="text-sm text-hutan/70 dark:text-santan/70">{{ $subheading ?? '' }}</p>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
