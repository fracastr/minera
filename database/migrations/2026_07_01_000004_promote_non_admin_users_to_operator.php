<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class PromoteNonAdminUsersToOperator extends Migration
{
    public function up()
    {
        DB::table('users')
            ->where(function ($query) {
                $query->where('role', '!=', 'admin')
                    ->orWhereNull('role');
            })
            ->update(['role' => 'operator']);
    }

    public function down()
    {
        DB::table('users')
            ->where('role', 'operator')
            ->update(['role' => 'viewer']);
    }
}
