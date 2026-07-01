<?php

namespace App\Support;

use App\Rules\StrongPassword;

class PasswordRules
{
    public static function required(): array
    {
        return ['required', 'string', new StrongPassword()];
    }

    public static function sometimes(): array
    {
        return ['sometimes', 'string', new StrongPassword()];
    }

    public static function sometimesConfirmed(): array
    {
        return ['sometimes', 'string', 'confirmed', new StrongPassword()];
    }

    public static function requiredConfirmed(): array
    {
        return array_merge(self::required(), ['confirmed']);
    }
}
