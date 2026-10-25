<?php

namespace App\Notifications;

use App\Models\Dormitory;
use Illuminate\Support\Facades\Route;

/**
 * Peserta belum absen asrama: daftar ke pengelola (audience staff) atau kabar ke wali (audience guardian).
 */
class DormAbsenceNotification extends WokabulariNotification
{
    protected string $icon = 'moon';

    protected string $level = 'warning';

    /**
     * @param  list<string>  $names
     */
    public function __construct(public Dormitory $dormitory, public array $names, public string $audience = 'staff', public bool $afterCurfew = false)
    {
        $this->viaWhatsApp = $audience === 'guardian';
        $this->level = $afterCurfew ? 'danger' : 'warning';
    }

    public function title(): string
    {
        if ($this->audience === 'guardian') {
            return $this->names[0].' belum tercatat di asrama';
        }

        return count($this->names).' peserta belum absen '.$this->dormitory->name;
    }

    public function body(): string
    {
        if ($this->audience === 'guardian') {
            return 'Sampai jam malam ('.substr((string) $this->dormitory->curfew_time, 0, 5).' WITA), anak Bapak/Ibu belum tercatat hadir di '.$this->dormitory->name.'. Petugas asrama sedang memastikan.';
        }

        return implode(', ', array_slice($this->names, 0, 15)).(count($this->names) > 15 ? ', dan lainnya' : '').'.';
    }

    public function url(): ?string
    {
        $route = $this->audience === 'guardian' ? 'guardian.attendance' : 'dorm.manage.attendance';

        return Route::has($route) ? route($route) : null;
    }
}
