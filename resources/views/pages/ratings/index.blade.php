<?php

use App\Actions\Ratings\SubmitRating;
use App\Http\Requests\MealScheduleRequest;
use App\Models\TeacherRating;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorRating;
use App\Services\RatingTargets;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rating mingguan')] class extends Component {
    /** "vendor:{id}" atau "teacher:{id}" yang sedang diisi. */
    public ?string $target = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function open(string $type, int $id): void
    {
        $this->authorize('create', TeacherRating::class);
        $this->resetErrorBag();
        $aspects = $type === 'teacher' ? TeacherRating::ASPECTS : VendorRating::ASPECTS;
        $this->target = $type.':'.$id;
        $this->form = ['rating' => null, 'comment' => '', 'suggestion' => '']
            + array_fill_keys(array_keys($aspects), null)
            + ($type === 'teacher' ? ['is_anonymous' => true] : []);
    }

    public function close(): void
    {
        $this->target = null;
    }

    public function submit(SubmitRating $submit): void
    {
        $this->authorize('create', TeacherRating::class);
        $this->resetErrorBag();
        [$type, $id] = explode(':', (string) $this->target) + [null, null];
        $teacher = $type === 'teacher';

        $validator = Validator::make($this->form, MealScheduleRequest::ratingRules(array_keys($teacher ? TeacherRating::ASPECTS : VendorRating::ASPECTS), $teacher), [
            'rating.required' => 'Pilih rating keseluruhan dulu.',
        ], ['rating' => 'rating']);

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $key => $messages) {
                $this->addError('form.'.$key, $messages[0]);
            }

            return;
        }

        try {
            $teacher
                ? $submit->teacher(auth()->user(), User::query()->findOrFail($id), $validator->validated())
                : $submit->vendor(auth()->user(), Vendor::query()->findOrFail($id), $validator->validated());
        } catch (\InvalidArgumentException $e) {
            $this->addError('form.rating', $e->getMessage());

            return;
        }

        $this->target = null;
        Flux::toast(variant: 'success', text: 'Makasih banyak! Rating ngana so tersimpan.');
    }

    public function with(RatingTargets $targets): array
    {
        $user = auth()->user();

        return [
            'week' => SubmitRating::weekStart(),
            'vendors' => $targets->vendors($user),
            'teachers' => $targets->teachers($user),
            'vendorAspects' => VendorRating::ASPECTS,
            'teacherAspects' => TeacherRating::ASPECTS,
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Rating mingguan" subtitle="Minggu {{ tanggal($week, 'j M') }} – {{ tanggal($week->endOfWeek()) }}. Sekali per minggu untuk tiap vendor dan pengajar." />

    @php($pending = $vendors->whereNull('rating')->count() + $teachers->whereNull('rating')->count())
    @if ($pending === 0 && ($vendors->isNotEmpty() || $teachers->isNotEmpty()))
        <flux:callout icon="check-badge" color="green" class="mb-4">
            <flux:callout.text>Semua rating minggu ini so terisi. Makasih, ngana pe masukan sangat membantu!</flux:callout.text>
        </flux:callout>
    @endif

    @foreach ([['vendor', 'Vendor makanan', $vendors, $vendorAspects, 'heart'], ['teacher', 'Pengajar', $teachers, $teacherAspects, 'star']] as [$type, $heading, $items, $aspects, $icon])
        <h2 class="mb-2 mt-5 text-sm font-bold uppercase tracking-wide text-hutan/60">{{ $heading }}</h2>
        <div class="space-y-3">
            @forelse ($items as $item)
                @php($model = $type === 'vendor' ? $item['vendor'] : $item['teacher'])
                @php($key = $type.':'.$model->id)
                <x-card wire:key="t-{{ $key }}">
                    <div class="flex items-center gap-3">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-woka-50 text-woka dark:bg-hutan-800 dark:text-woka-light">
                            <flux:icon :name="$type === 'vendor' ? 'building-storefront' : 'academic-cap'" class="size-6" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $model->name }}</p>
                            <p class="text-xs text-hutan/60">{{ $type === 'teacher' ? $item['classroom']->name : 'Konsumsi minggu ini' }}</p>
                        </div>
                        @if ($item['rating'])
                            <flux:badge color="green" size="sm" icon="check">{{ $item['rating']->rating }}/5</flux:badge>
                        @elseif ($target !== $key)
                            <flux:button size="sm" variant="primary" wire:click="open('{{ $type }}', {{ $model->id }})">Beri rating</flux:button>
                        @endif
                    </div>

                    @if ($target === $key && ! $item['rating'])
                        <form wire:submit="submit" class="mt-4 space-y-4 border-t border-woka-100 pt-4 dark:border-hutan-800">
                            <div>
                                <x-star-rating model="form.rating" label="Rating keseluruhan" :icon="$icon" />
                                <flux:error name="form.rating" />
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                @foreach ($aspects as $aspect => $label)
                                    <x-star-rating :model="'form.'.$aspect" :label="$label" size="size-6" wire:key="as-{{ $key }}-{{ $aspect }}" />
                                @endforeach
                            </div>
                            <flux:textarea wire:model="form.comment" label="Komentar" rows="2" placeholder="Apa yang so bae, apa yang kurang?" />
                            <flux:textarea wire:model="form.suggestion" label="Saran" rows="2" />
                            @if ($type === 'teacher')
                                <flux:switch wire:model="form.is_anonymous" label="Kirim sebagai anonim" description="Pengajar tidak melihat nama ngana. Admin program tetap bisa melihat." />
                            @endif
                            <div class="flex justify-end gap-2">
                                <flux:button variant="ghost" wire:click="close">Batal</flux:button>
                                <flux:button type="submit" variant="primary">Kirim rating</flux:button>
                            </div>
                        </form>
                    @endif
                </x-card>
            @empty
                <x-empty-state :icon="$type === 'vendor' ? 'building-storefront' : 'academic-cap'"
                               :title="$type === 'vendor' ? 'Belum ada vendor minggu ini' : 'Belum ada pengajar kelas'"
                               :description="$type === 'vendor' ? 'Rating vendor dibuka setelah ada jadwal makan minggu ini.' : 'Rating pengajar dibuka untuk pengajar kelas ngana.'" />
            @endforelse
        </div>
    @endforeach
</div>
