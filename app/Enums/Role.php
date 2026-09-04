<?php

namespace App\Enums;

enum Role: string
{
    case Sa = 'sa';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Sa => 'ผู้ดูแลระบบ (SA)',
            self::Admin => 'เจ้าหน้าที่ (Admin)',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $role) => [$role->value => $role->label()]
        )->all();
    }
}
