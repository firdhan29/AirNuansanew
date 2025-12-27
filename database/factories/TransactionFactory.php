<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        // Tentukan tipe dulu (Masuk/Keluar)
        $type = fake()->randomElement(['income', 'expense']);

        return [
            'type' => $type,
            'category' => $type === 'income' ? 'Iuran Warga' : fake()->randomElement(['Perbaikan Pipa', 'Token Listrik', 'Kebersihan']),
            'amount' => $type === 'income' ? 150000 : fake()->numberBetween(50000, 1000000),
            'date' => fake()->dateTimeBetween('-3 months', 'now'), // Tanggal 3 bulan terakhir
            'description' => fake()->sentence(4), // Keterangan acak
        ];
    }
}