<?php

namespace App\Livewire\Concerns;

use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\WithPagination;

/**
 * Pola CRUD standar: tabel + modal form. Komponen cukup mendefinisikan model, nilai awal form,
 * aturan validasi (dari Form Request), dan label kolom.
 *
 * Data form disimpan di $form; aturan otomatis diberi awalan "form.".
 */
trait WithCrudModal
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $search = '';

    /** @var array<string, mixed> */
    public array $form = [];

    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** @return array<string, mixed> Nilai awal form. */
    abstract protected function defaults(): array;

    /** @return array<string, mixed> Aturan validasi tanpa awalan. */
    abstract protected function formRules(): array;

    /** @return array<string, string> Label field untuk pesan validasi. */
    protected function labels(): array
    {
        return [];
    }

    public function mountWithCrudModal(): void
    {
        $this->form = $this->defaults();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', $this->modelClass());
        $this->resetValidation();
        $this->editingId = null;
        $this->form = $this->defaults();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $model = $this->findModel($id);
        $this->authorize('update', $model);
        $this->resetValidation();
        $this->editingId = $model->getKey();
        $this->form = array_merge($this->defaults(), $this->fillFrom($model));
        $this->showForm = true;
    }

    public function save(): void
    {
        $model = $this->editingId ? $this->findModel($this->editingId) : null;
        $this->authorize($model ? 'update' : 'create', $model ?? $this->modelClass());

        $this->persist($this->validateForm(), $model);

        $this->showForm = false;
        Flux::toast(variant: 'success', text: $model ? 'Perubahan tersimpan.' : 'Data baru tersimpan. Mantap!');
    }

    public function delete(int $id): void
    {
        $model = $this->findModel($id);
        $this->authorize('delete', $model);
        $this->beforeDelete($model);
        $model->delete();

        Flux::toast(variant: 'success', text: 'Data dihapus.');
    }

    /**
     * Validasi $form apa adanya (agar aturan seperti after:start_date tetap bekerja),
     * lalu tampilkan galat dengan kunci "form.*".
     *
     * @return array<string, mixed>
     */
    protected function validateForm(): array
    {
        $validator = Validator::make($this->form, $this->formRules(), [], $this->labels());

        if ($validator->fails()) {
            throw ValidationException::withMessages(
                collect($validator->errors()->messages())->mapWithKeys(fn ($messages, $key) => ["form.{$key}" => $messages])->all()
            );
        }

        return $validator->validated();
    }

    protected function findModel(int $id): Model
    {
        return $this->modelClass()::query()->findOrFail($id);
    }

    /**
     * Nilai form saat mengedit.
     *
     * @return array<string, mixed>
     */
    protected function fillFrom(Model $model): array
    {
        return collect(array_keys($this->defaults()))
            ->mapWithKeys(function (string $key) use ($model) {
                $value = $model->getAttribute($key);

                return [$key => match (true) {
                    $value instanceof \BackedEnum => $value->value,
                    $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                    default => $value,
                }];
            })
            ->all();
    }

    /**
     * Simpan data tervalidasi.
     *
     * @param  array<string, mixed>  $data
     */
    protected function persist(array $data, ?Model $model): void
    {
        $model ??= new ($this->modelClass());
        $model->fill($data)->save();
    }

    protected function beforeDelete(Model $model): void
    {
        //
    }
}
