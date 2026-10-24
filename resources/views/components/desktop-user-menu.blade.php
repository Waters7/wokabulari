@php($user = auth()->user())
<flux:dropdown position="bottom" align="start" {{ $attributes }}>
    <flux:sidebar.profile
        :name="$user->name"
        :initials="$user->initials()"
        :avatar="$user->photo_url"
        icon:trailing="chevrons-up-down"
        data-test="sidebar-menu-button"
    />

    <x-user-menu-items />
</flux:dropdown>
