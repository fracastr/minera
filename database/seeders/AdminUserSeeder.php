<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        if (User::where('role', 'admin')->exists()) {
            return;
        }

        $email = env('ADMIN_EMAIL', 'admin@sondek.cl');
        $password = env('ADMIN_PASSWORD', 'Changeme123!');
        $name = env('ADMIN_NAME', 'Administrador');

        User::updateOrCreate(
            ['email' => $email],
            [
                'nombre' => $name,
                'password' => Hash::make($password),
                'role' => 'admin',
                'activo' => true,
            ]
        );
    }
}
