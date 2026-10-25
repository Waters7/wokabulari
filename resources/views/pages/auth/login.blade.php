<x-layouts::auth title="Masuk">
    <div class="flex flex-col gap-6">
        <div class="text-center">
            <h1 class="font-display text-2xl font-extrabold text-hutan dark:text-santan">Masuk ke Wokabulari</h1>
            <p class="mt-1 text-sm text-hutan/70 dark:text-santan/70">Selamat datang! Ngana so siap blajar?</p>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

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
                    placeholder="Kata sandi"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute end-0 top-0 text-sm" :href="route('password.request')" wire:navigate>
                        Lupa kata sandi?
                    </flux:link>
                @endif
            </div>

            <flux:checkbox name="remember" label="Ingat saya" :checked="old('remember')" />

            <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                Masuk
            </flux:button>
        </form>

        <p class="text-center text-xs text-hutan/60 dark:text-santan/60">
            Masuk pakai No. HP atau email yang terdaftar di profil.<br>
            Peserta baru: pakai No. HP dan kata sandi awal dari panitia, lalu ganti kata sandi.
        </p>
        <p class="text-center text-xs text-hutan/60 dark:text-santan/60">
            Orang tua/wali punya kode undangan? <a href="{{ route('guardian.register') }}" wire:navigate class="font-semibold text-woka underline dark:text-woka-light">Daftar akun wali</a>
        </p>
    </div>
</x-layouts::auth>
