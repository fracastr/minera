<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateAdminEmailToSondek extends Migration
{
    public function up()
    {
        DB::table('users')
            ->where('email', 'fr.castr@gmail.com')
            ->update(['email' => 'admin@sondek.cl']);
    }

    public function down()
    {
        DB::table('users')
            ->where('email', 'admin@sondek.cl')
            ->update(['email' => 'fr.castr@gmail.com']);
    }
}
