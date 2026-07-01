<?php

namespace App\Services;

class UserAbilityService
{
    public static function getAbilitiesForRole(string $role): array
    {
        switch ($role) {
            case 'admin':
                return [
                    ['action' => 'manage', 'subject' => 'all'],
                ];
            case 'operator':
                return [
                    ['action' => 'read', 'subject' => 'Balances'],
                    ['action' => 'create', 'subject' => 'Balances'],
                    ['action' => 'update', 'subject' => 'Balances'],
                    ['action' => 'read', 'subject' => 'Auth'],
                ];
            case 'viewer':
            default:
                return [
                    ['action' => 'read', 'subject' => 'Balances'],
                    ['action' => 'read', 'subject' => 'Auth'],
                ];
        }
    }

    public static function allowedRoles(): array
    {
        return ['admin', 'operator', 'viewer'];
    }
}
