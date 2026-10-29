<x-layouts::auth title="Masuk">
    <div class="flex flex-col gap-8">
        <div>
            <h1 class="font-display text-3xl font-extrabold tracking-tight text-hutan dark:text-santan">Masuk</h1>
            <p class="mt-1 text-sm text-hutan/60 dark:text-santan/60">Kaya Kosakata, Torang Go Global!</p>
        </div>

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <flux:input
                name="login"
                label="No. HP, email, atau NIS/NIP"
                :value="old('login')"
                type="text"
                required
                autofocus
                autocomplete="username"
                autocapitalize="none"
                placeholder="08xxxxxxxxxx"
            />

            <div class="relative">
                <flux:input
                    name="password"
                    label="Kata sandi"
                    type="password"
                    required
                    autocomplete="current-password"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute end-0 top-0 text-sm" :href="route('password.request')" wire:navigate>
                        Lupa?
                    </flux:link>
                @endif
            </div>

            <flux:checkbox name="remember" label="Ingat saya" :checked="old('remember')" />

            <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                Masuk
            </flux:button>
        </form>

        <div class="space-y-2 border-t border-zinc-100 pt-5 text-sm text-hutan/55 dark:border-hutan-800 dark:text-santan/55">
            <p>Masuk pakai No. HP atau email yang terdaftar di profil. Peserta baru: pakai kata sandi awal dari panitia.</p>
            <p>
                Orang tua/wali?
                <a href="{{ route('guardian.register') }}" wire:navigate class="font-semibold text-woka hover:underline dark:text-woka-light">Daftar dengan kode undangan</a>
            </p>
        </div>
    </div>
</x-layouts::auth>
