<?php

namespace App\Support;

final class Roles
{
    public const USER = 'user';
    public const ADMIN = 'admin';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::USER,
            self::ADMIN,
        ];
    }
}
