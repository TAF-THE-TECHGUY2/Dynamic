<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Grade;
use Illuminate\Database\Seeder;

class GradeSeeder extends Seeder
{
    public function run(): void
    {
        $grades = [
            [
                'id' => '50ppm',
                'label' => 'Diesel 50ppm',
                'code' => 'D50-BULK',
                'blurb' => 'Low sulphur',
                'sort' => 1,
            ],
            [
                'id' => '500ppm',
                'label' => 'Diesel 500ppm',
                'code' => 'D500-BULK',
                'blurb' => 'Standard',
                'sort' => 2,
            ],
        ];

        foreach ($grades as $grade) {
            Grade::query()->updateOrCreate(['id' => $grade['id']], $grade + ['active' => true]);
        }
    }
}
