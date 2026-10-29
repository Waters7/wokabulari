<?php

use App\Actions\Participants\UpdateBiodata;
use App\Enums\Gender;
use App\Http\Requests\BiodataRequest;
use App\Support\Phone;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Biodata')] class extends Component {
    /** @var array<string, string> */
    public array $form = [];

    public bool $genderLocked = false;

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user->isParticipant(), 403);
        $this->authorize('updateBiodata', $user);

        $profile = $user->profile;
        $this->genderLocked = filled($profile?->gender);
        $this->form = [
            'birth_place' => (string) $profile?->birth_place,
            'birth_date' => (string) $profile?->birth_date?->toDateString(),
            'gender' => (string) $profile?->gender?->value,
            'address' => (string) $profile?->address,
            'school_name' => (string) $profile?->school_name,
            'school_grade' => (string) $profile?->school_grade,
            'major' => (string) $profile?->major,
            'blood_type' => (string) $profile?->blood_type,
            'allergies' => (string) $profile?->allergies,
            'medical_history' => (string) $profile?->medical_history,
            'emergency_contact_name' => (string) $profile?->emergency_contact_name,
            'emergency_contact_phone' => $profile?->emergency_contact_phone ? Phone::display($profile->emergency_contact_phone) : '',
            'shirt_size' => (string) $profile?->shirt_size,
            'target_exam' => (string) $profile?->target_exam,
            'target_score' => (string) $profile?->target_score,
        ];
    }

    public function save(UpdateBiodata $update): void
    {
        $user = Auth::user();
        $this->authorize('updateBiodata', $user);
        $this->resetErrorBag();

        $data = $this->genderLocked ? collect($this->form)->except('gender')->all() : $this->form;
        $validator = Validator::make($data, BiodataRequest::rulesFor($this->genderLocked), [], BiodataRequest::attributesFor());
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ['form.'.$k => $m])->all());
        }

        $profile = $update->handle($user, $validator->validated());
        $this->genderLocked = filled($profile->gender);
        Flux::toast(variant: 'success', text: 'Biodata tersimpan ('.$profile->biodataCompletion().'% lengkap). Mantap!');
    }

    public function with(): array
    {
        $profile = Auth::user()->profile;

        return [
            'completion' => $profile?->biodataCompletion() ?? 0,
            'updatedAt' => $profile?->biodata_updated_at,
            'genders' => Gender::cases(),
            'grades' => BiodataRequest::SCHOOL_GRADES,
            'bloodTypes' => BiodataRequest::BLOOD_TYPES,
            'shirtSizes' => BiodataRequest::SHIRT_SIZES,
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout heading="Biodata" subheading="Lengkapi data diri ngana. Data kesehatan hanya dilihat petugas kesehatan & asrama.">
        <div class="mb-6 rounded-2xl bg-white p-4 ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800" data-test="biodata-completion">
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="font-semibold">Kelengkapan biodata</span>
                <span class="font-bold tabular-nums">{{ $completion }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-hutan-800" role="progressbar" aria-valuenow="{{ $completion }}" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-woka" style="width: {{ $completion }}%"></div>
            </div>
            @if ($updatedAt)
                <p class="mt-2 text-xs text-hutan/50 dark:text-santan/50">Terakhir diperbarui {{ tanggal_jam($updatedAt) }}</p>
            @endif
        </div>

        <form wire:submit="save" class="space-y-8">
            <fieldset class="space-y-4">
                <legend class="mb-1 text-xs font-semibold uppercase tracking-[0.12em] text-hutan/45 dark:text-santan/45">Data diri</legend>
                <flux:input :value="auth()->user()->name" label="Nama lengkap" disabled description="Nama diatur admin program sesuai data resmi." />
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="form.birth_place" label="Tempat lahir" placeholder="Manado" />
                    <flux:input wire:model="form.birth_date" type="date" label="Tanggal lahir" />
                </div>
                @if ($genderLocked)
                    <flux:input :value="Gender::from($form['gender'])->label()" label="Jenis kelamin" disabled description="Hubungi admin program bila keliru." />
                @else
                    <flux:radio.group wire:model="form.gender" label="Jenis kelamin" variant="segmented" description="Hanya bisa diisi sekali; dipakai untuk pembagian kamar asrama.">
                        @foreach ($genders as $gender)
                            <flux:radio :value="$gender->value" :label="$gender->label()" class="data-checked:bg-woka! data-checked:text-white!" />
                        @endforeach
                    </flux:radio.group>
                @endif
                <flux:textarea wire:model="form.address" label="Alamat asal" rows="2" placeholder="Jl. Sam Ratulangi No. 10, Kel. Wenang, Manado" />
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="mb-1 text-xs font-semibold uppercase tracking-[0.12em] text-hutan/45 dark:text-santan/45">Sekolah</legend>
                <flux:input wire:model="form.school_name" label="Asal sekolah" placeholder="SMK Negeri 1 Manado" />
                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="form.school_grade" label="Kelas" placeholder="Pilih">
                        @foreach ($grades as $grade)
                            <flux:select.option :value="$grade">{{ $grade }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="form.major" label="Jurusan" placeholder="Keperawatan" />
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="mb-1 text-xs font-semibold uppercase tracking-[0.12em] text-hutan/45 dark:text-santan/45">Kesehatan</legend>
                <flux:select wire:model="form.blood_type" label="Golongan darah" placeholder="Pilih">
                    @foreach ($bloodTypes as $bloodType)
                        <flux:select.option :value="$bloodType">{{ $bloodType }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:textarea wire:model="form.allergies" label="Alergi / pantangan makanan" rows="2" placeholder="Udang, kacang tanah (kosongkan bila tidak ada)" description="Diteruskan ke tim konsumsi." />
                <flux:textarea wire:model="form.medical_history" label="Riwayat penyakit" rows="2" placeholder="Asma ringan, membawa inhaler (kosongkan bila tidak ada)" />
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="mb-1 text-xs font-semibold uppercase tracking-[0.12em] text-hutan/45 dark:text-santan/45">Kontak darurat</legend>
                <flux:text class="text-sm">Selain orang tua/wali (yang diisi di Profil), mis. kakak atau kerabat di Manado.</flux:text>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="form.emergency_contact_name" label="Nama" placeholder="Om Robert" />
                    <flux:input wire:model="form.emergency_contact_phone" type="tel" inputmode="tel" label="No. HP" placeholder="0812xxxxxxxx" />
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="mb-1 text-xs font-semibold uppercase tracking-[0.12em] text-hutan/45 dark:text-santan/45">Lainnya</legend>
                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="form.shirt_size" label="Ukuran baju" placeholder="Pilih">
                        @foreach ($shirtSizes as $size)
                            <flux:select.option :value="$size">{{ $size }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="form.target_exam" label="Target ujian" placeholder="IELTS / JLPT N4" />
                </div>
                <flux:input wire:model="form.target_score" label="Target skor" placeholder="5.5" />
            </fieldset>

            <flux:button type="submit" variant="primary" class="w-full sm:w-auto">Simpan biodata</flux:button>
        </form>
    </x-pages::settings.layout>
</section>
