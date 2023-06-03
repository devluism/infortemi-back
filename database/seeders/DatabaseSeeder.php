<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        $this->call(MembershipsPackageSeeder::class);
        $this->call(PrefixSeeder::class);
        $this->call(UserSeeder::class);
        // $this->call(OrderSeeder::class);
        // $this->call(ProjectSeeder::class);
        // $this->call(WalletComisionSeeder::class);
        // $this->call(KycSeeder::class);
        // $this->call(TicketSeeder::class);
        // $this->call(MembershipSeeder::class);
        // $this->call(LiquidactionSeeder::class);
        // $this->call(DocumentSeeder::class);
    }
}
