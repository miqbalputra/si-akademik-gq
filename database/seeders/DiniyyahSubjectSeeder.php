<?php

namespace Database\Seeders;

use App\Models\DiniyyahSubject;
use Illuminate\Database\Seeder;

class DiniyyahSubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['code' => 'akidah_akhlak', 'name' => 'Akidah Akhlak', 'default_assessment_method' => 'weighted', 'sort_order' => 10],
            ['code' => 'bahasa_arab', 'name' => 'Bahasa Arab', 'default_assessment_method' => 'weighted', 'sort_order' => 20],
            ['code' => 'fiqih', 'name' => 'Fiqih', 'default_assessment_method' => 'weighted', 'sort_order' => 30],
            ['code' => 'khat', 'name' => 'Khat', 'default_assessment_method' => 'weighted', 'sort_order' => 40],
            ['code' => 'suluk', 'name' => 'Suluk', 'default_assessment_method' => 'direct_final', 'sort_order' => 50],
            ['code' => 'praktik_ibadah', 'name' => 'Praktik Ibadah', 'default_assessment_method' => 'practical', 'sort_order' => 60],
        ];

        foreach ($subjects as $subject) {
            DiniyyahSubject::updateOrCreate(
                ['code' => $subject['code']],
                $subject + ['is_active' => true],
            );
        }
    }
}
