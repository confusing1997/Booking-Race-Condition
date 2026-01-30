<?php

namespace Database\Seeders;

use App\Models\Ticket;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
      Ticket::create([
        "name"=> "Vé Concert BlackPink (Limited VIP)",
        'quantity'=> 100
      ]);
    }
}
