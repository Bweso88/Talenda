<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

use App\Models\User;
use App\Modules\Academic\Models\StaffMember;
use App\Modules\Core\Tenancy\TenantContext;

/**
 * Fiche « personnel » de l'utilisateur dans l'école active : c'est elle qui
 * est affectée aux matières des classes (class_subjects.teacher_id).
 */
final class TeacherIdentity
{
    /**
     * @var array<string, int|null>
     */
    private static array $cache = [];

    public static function staffMemberId(User $user): ?int
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            return null;
        }

        return self::$cache["{$tenantId}:{$user->id}"] ??= StaffMember::query()
            ->whereHas('membership', fn ($query) => $query->where('user_id', $user->id))
            ->value('id');
    }

    /**
     * Pour les tests et les longs processus.
     */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
