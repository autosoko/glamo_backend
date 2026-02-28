<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // NB: Hatu-truncate users kwa sababu inaweza kuwa referenced sehemu nyingine.
        // Tuna-insert 50 wapya tu.

        $firstNames = [
            'Asha','Neema','Rehema','Joyce','Wema','Halima','Anna','Zawadi','Maria','Judith',
            'Beatrice','Agness','Sophia','Hawa','Fatma','Janeth','Rose','Ester','Leah','Sofia'
        ];
        $lastNames = [
            'Mushi','Nnko','Kimaro','Mollel','Rashid','John','Peter','Mrema','Msuya','Kweka',
            'Masawe','Malo','Said','Mohamed','Mfaume','Mashauri','Laiser','Sanka','Lema','Laizer'
        ];

        $rows = [];

        for ($i=1; $i<=50; $i++) {
            $name = $firstNames[array_rand($firstNames)].' '.$lastNames[array_rand($lastNames)];
            $phone = '255'.mt_rand(620000000, 799999999);

            // Kama users table yako haina phone, niambie—nitaku-adjust.
            // Email tunaiweka unique
            $email = 'provider'.$i.'_'.$phone.'@glamo.local';

            $rows[] = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make('password'), // kwa test tu
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('users')->insert($rows);
    }
}
