<?php

namespace App\Notifications;

use App\Models\SessionMaterial;

class MaterialPublished extends WokabulariNotification
{
    protected string $icon = 'book-open';

    public function __construct(public SessionMaterial $material) {}

    public function title(): string
    {
        return 'Materi baru tersedia';
    }

    public function body(): string
    {
        $session = $this->material->session;

        return "{$this->material->title}".($session ? " · {$session->topic}" : '');
    }

    public function url(): ?string
    {
        return route('materials.show', $this->material);
    }
}
