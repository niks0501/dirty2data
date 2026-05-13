<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            'name' => 'Nikko Causapin',
            'email' => 'nikkocausapin@gmail.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);
    }
}
