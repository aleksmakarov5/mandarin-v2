<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'last_name',
        'name',
        'patronymic',
        'email',
        'phone',
        'user_group_id',
        'city_id',
        'is_system_admin',
        'def_mrk_proc',
        'def_zap_proc',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_system_admin' => 'boolean',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $appends = [
        'full_name',
        'short_name',
    ];

    // Полное ФИО: "Иванов Иван Иванович"
    public function getFullNameAttribute(): string
    {
        return trim("{$this->last_name} {$this->name} {$this->patronymic}");
    }

    // Фамилия и инициалы: "Иванов И. И."
    public function getShortNameAttribute(): string
    {
        $initials = '';
        if ($this->name) {
            $initials .= mb_substr(trim($this->name), 0, 1) . '.';
        }
        if ($this->patronymic) {
            $initials .= ' ' . mb_substr(trim($this->patronymic), 0, 1) . '.';
        }
        return trim("{$this->last_name} {$initials}");
    }

    /**
     * Основная роль/группа пользователя
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }

    /**
     * Основной город пользователя
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    /**
     * Доступные города (многие-ко-многим)
     */
    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class, 'city_user');
    }

    /**
     * Роли пользователя по филиалам
     */
    public function branchRoles(): HasMany
    {
        return $this->hasMany(OurCompanyUserRole::class);
    }

    /**
     * Филиалы компании
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(OurCompany::class, 'our_company_user_role')
            ->withPivot('user_group_id', 'is_active')
            ->withTimestamps();
    }

    /**
     * Проверка на системного администратора
     */
    public function isSystemAdmin(): bool
    {
        return (bool) $this->is_system_admin || $this->group?->code === 'sys_admin';
    }

    /**
     * Проверка: является ли пользователь владельцем филиала
     */
    public function isBranchOwner(int $branchId): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }

        return $this->hasRoleInBranch($branchId, 'owner');
    }

    /**
     * Проверка роли в конкретном филиале
     */
    public function hasRoleInBranch(
        int $branchId,
        string $roleCode
    ): bool {
        return $this->isSystemAdmin()
            || $this->hasBranchRole($roleCode, $branchId);
    }

    /**
     * Получить ID доступных филиалов
     */
    public function getAvailableBranchIds(): array
    {
        if ($this->isSystemAdmin()) {
            return OurCompany::pluck('id')->toArray();
        }

        return $this->branchRoles()
            ->where('is_active', true)
            ->pluck('our_company_id')
            ->unique()
            ->toArray();
    }

    public function service(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Service::class, 'user_id');
    }
    /**
     * Проверка активных назначений ролей.
     *
     * $roles:
     *   5, 6, 8, 9 — ID ролей;
     *   'admin', 'owner', 'mrk' — коды ролей.
     *
     * При указанном филиале проверяем ТОЛЬКО его назначения.
     * Без филиала ищем активное назначение в любом филиале.
     *
     * Основная группа пользователя не даёт доступ ко всем филиалам.
     */
    public function hasBranchRole(
        int|string|array $roles,
        ?int $branchId = null
    ): bool {
        $roles = is_array($roles) ? $roles : [$roles];

        $roleIds = [];
        $roleCodes = [];

        foreach ($roles as $role) {
            if (
                is_int($role) || (
                    is_string($role) && ctype_digit($role)
                )
            ) {
                $roleIds[] = (int) $role;
            } elseif (is_string($role) && trim($role) !== '') {
                $roleCodes[] = trim($role);
            }
        }

        if ($roleIds === [] && $roleCodes === []) {
            return false;
        }

        if ($branchId !== null && $branchId <= 0) {
            return false;
        }

        $query = $this->branchRoles()
            ->where('is_active', true);

        if ($branchId !== null) {
            $query->where('our_company_id', $branchId);
        }

        return $query->where(function ($query) use ($roleIds, $roleCodes) {
            if ($roleIds !== []) {
                $query->whereIn('user_group_id', $roleIds);
            }

            if ($roleCodes !== []) {
                $condition = function ($roleQuery) use ($roleCodes) {
                    $roleQuery->whereIn('code', $roleCodes);
                };

                if ($roleIds !== []) {
                    $query->orWhereHas('role', $condition);
                } else {
                    $query->whereHas('role', $condition);
                }
            }
        })->exists();
    }

    public function isSto(?int $branchId = null): bool
    {
        if ($this->isSystemAdmin()) {
            return false;
        }

        // 1. Проверяем роль 8 (СТО) в связующей таблице филиала
        if ($this->hasBranchRole(8, $branchId)) {
            return true;
        }

        // 2. Проверяем наличие привязанной карточки автосервиса (Service)
        if ($this->service()->exists()) {
            // Если передан конкретный филиал — проверяем, привязан ли сервис к нему
            if ($branchId !== null) {
                return $this->service()
                    ->whereHas('branches', fn($q) => $q->where('our_companies.id', $branchId))
                    ->exists();
            }

            return true;
        }

        return false;
    }

    public function getServiceId(): ?int
    {
        // 1. Через прямую связь hasOne
        if ($this->relationLoaded('service') && $this->service) {
            return (int) $this->service->id;
        }

        $service = $this->service()->first();
        if ($service) {
            return (int) $service->id;
        }

        // 2. Фоллбэк: прямой запрос в таблицу services
        $serviceId = \Illuminate\Support\Facades\DB::table('services')
            ->where('user_id', $this->id)
            ->value('id');

        return $serviceId ? (int) $serviceId : null;
    }

    /**
     * Проверка: является ли пользователь запчастистом (СПМ)
     */
    public function isSpm(?int $branchId = null): bool
    {
        return !$this->isSystemAdmin()
            && $this->hasBranchRole(9, $branchId);
    }

    /**
     * Проверка: является ли пользователь МРК
     */
    public function isMrk(?int $branchId = null): bool
    {
        return !$this->isSystemAdmin()
            && $this->hasBranchRole(5, $branchId);
    }

    /**
     * Есть ли у пользователя активная роль,
     * отличная от СТО и запчастиста.
     */
    public function hasBackOfficeRole(): bool
    {
        return $this->branchRoles()
            ->where('is_active', true)
            ->whereNotIn('user_group_id', [8, 9])
            ->whereHas('role')
            ->exists();
    }

    /**
     * Режим пользователя для главного меню.
     */
    public function menuMode(): string
    {
        if ($this->isSystemAdmin()) {
            return 'admin';
        }

        if ($this->hasBackOfficeRole()) {
            return 'backoffice';
        }

        if ($this->isSpm()) {
            return 'spm';
        }

        if ($this->isSto()) {
            return 'service';
        }

        return 'none';
    }

    /**
     * Разрешения меню.
     */
    public function menuPermissions(): array
    {
        $mode = $this->menuMode();

        return [
            'requests' => in_array($mode, ['admin', 'backoffice', 'spm', 'service'], true),
            'applications' => in_array($mode, ['admin', 'backoffice', 'spm', 'service'], true),
            'chat' => $mode !== 'none',
            'books' => $mode !== 'none',
            'help' => $mode !== 'none',

            'warehouse' => in_array($mode, ['admin', 'backoffice', 'spm'], true),

            'acceptance' => in_array($mode, ['admin', 'backoffice'], true),
            'archive' => in_array($mode, ['admin', 'backoffice'], true),
            'trash' => in_array($mode, ['admin', 'backoffice'], true),
            'mail' => in_array($mode, ['admin', 'backoffice'], true),
            'finance' => in_array($mode, ['admin', 'backoffice'], true),

            'admin' => $mode === 'admin',
        ];
    }
}