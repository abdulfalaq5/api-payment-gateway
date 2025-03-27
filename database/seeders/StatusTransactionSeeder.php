<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\StatusTransactionModel;

class StatusTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        StatusTransactionModel::create([
            'id' => 1,
            'name' => 'pending',
        ]);
        StatusTransactionModel::create([
            'id' => 2,
            'name' => 'success',
        ]);
        StatusTransactionModel::create([
            'id' => 3,
            'name' => 'failed',
        ]);
    }
}
