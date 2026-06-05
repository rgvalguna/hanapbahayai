<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeveloperSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('developers')->insert([
            [
                'name'               => 'Ayala Land Inc.',
                'slug'               => 'ayala-land',
                'website'            => 'https://www.ayalaland.com.ph',
                'description'        => 'One of the largest property developers in the Philippines, known for master-planned communities.',
                'reputation_score'   => 92.50,
                'total_projects'     => 148,
                'completed_on_time'  => 130,
                'active_complaints'  => 2,
                'is_verified'        => true,
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'SM Development Corporation',
                'slug'               => 'smdc',
                'website'            => 'https://www.smdc.com',
                'description'        => 'Subsidiary of SM Prime Holdings focused on affordable-to-mid residential condominiums.',
                'reputation_score'   => 84.00,
                'total_projects'     => 92,
                'completed_on_time'  => 74,
                'active_complaints'  => 8,
                'is_verified'        => true,
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'DMCI Homes',
                'slug'               => 'dmci-homes',
                'website'            => 'https://www.dmcihomes.com',
                'description'        => 'Known for resort-themed mid-rise condominiums in Metro Manila.',
                'reputation_score'   => 88.75,
                'total_projects'     => 63,
                'completed_on_time'  => 57,
                'active_complaints'  => 3,
                'is_verified'        => true,
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
            [
                'name'               => 'Rockwell Land Corporation',
                'slug'               => 'rockwell-land',
                'website'            => 'https://www.rockwellland.com',
                'description'        => 'Premium residential and mixed-use developer; home of Rockwell Center in Makati.',
                'reputation_score'   => 95.00,
                'total_projects'     => 22,
                'completed_on_time'  => 21,
                'active_complaints'  => 0,
                'is_verified'        => true,
                'created_at'         => $now,
                'updated_at'         => $now,
            ],
        ]);
    }
}
