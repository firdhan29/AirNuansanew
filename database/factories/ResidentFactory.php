<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ResidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(), // Nama acak
            'block' => fake()->randomElement(['A', 'B', 'C', 'D']) . fake()->numberBetween(1, 20), // Contoh: A5, B12
            'phone' => fake()->phoneNumber(),
            // Ini membuat JSON riwayat bayar (Jan-Apr acak Lunas/Belum)
            'payment_history' => [
                'jan' => fake()->boolean(90), // 90% kemungkinan lunas
                'feb' => fake()->boolean(80),
                'mar' => fake()->boolean(70),
                'apr' => fake()->boolean(50),
                'may' => false, // Mei belum lunas
            ],
        ];
    }
}