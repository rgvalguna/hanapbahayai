<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ── Admin ──────────────────────────────────────────────────────────────
        $adminId = (string) Str::uuid();
        DB::table('users')->insert([
            'id'                   => $adminId,
            'name'                 => 'HanapBahay Admin',
            'email'                => 'admin@hanapbahay.ph',
            'email_verified_at'    => $now,
            'password'             => Hash::make('Admin1234!'),
            'is_admin'             => true,
            'is_verified'          => true,
            'onboarding_completed' => true,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        DB::table('user_profiles')->insert([
            'user_id'          => $adminId,
            'onboarding_step'  => 4,
            'purchase_purpose' => 'investment',
            'family_size'      => 1,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        // ── Test Buyer ─────────────────────────────────────────────────────────
        $buyerId = (string) Str::uuid();
        DB::table('users')->insert([
            'id'                   => $buyerId,
            'name'                 => 'Maria Santos',
            'email'                => 'buyer@hanapbahay.ph',
            'email_verified_at'    => $now,
            'password'             => Hash::make('Buyer1234!'),
            'is_ofw'               => false,
            'is_verified'          => true,
            'onboarding_completed' => true,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        DB::table('user_profiles')->insert([
            'user_id'          => $buyerId,
            'archetype'        => 'young_family',
            'onboarding_step'  => 4,
            'family_size'      => 4,
            'num_children'     => 2,
            'children_ages'    => json_encode([5, 8]),
            'purchase_purpose' => 'primary_home',
            'risk_tolerance'   => 'moderate',
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
        DB::table('user_finances')->insert([
            'user_id'                       => $buyerId,
            'version'                       => 1,
            'is_current'                    => true,
            'gross_monthly_income_php'      => 85000.00,
            'employment_type'               => 'employed',
            'has_co_borrower'               => true,
            'co_borrower_income_php'        => 45000.00,
            'monthly_obligations_php'       => 8000.00,
            'available_down_payment_php'    => 500000.00,
            'monthly_savings_php'           => 20000.00,
            'pagibig_member'                => true,
            'pagibig_contributions_months'  => 48,
            'pagibig_contributions_current' => true,
            'created_at'                    => $now,
            'updated_at'                    => $now,
        ]);
        DB::table('user_preferences')->insert([
            'user_id'            => $buyerId,
            'property_types'     => json_encode(['single_detached', 'townhouse']),
            'tenure_types'       => json_encode(['rfo', 'freehold']),
            'min_bedrooms'       => 3,
            'min_price_php'      => 3000000.00,
            'max_price_php'      => 8000000.00,
            'min_floor_area_sqm' => 60.00,
            'near_schools'       => true,
            'flood_averse'       => true,
            'needs_parking'      => true,
            'must_have_tags'     => json_encode(['parking', 'near_school']),
            'nice_to_have_tags'  => json_encode([]),
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);
        DB::table('shortlists')->insert([
            'user_id'    => $buyerId,
            'name'       => 'My Favorites',
            'is_default' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ── OFW Buyer ──────────────────────────────────────────────────────────
        $ofwId = (string) Str::uuid();
        DB::table('users')->insert([
            'id'                   => $ofwId,
            'name'                 => 'Jose Reyes',
            'email'                => 'ofw@hanapbahay.ph',
            'email_verified_at'    => $now,
            'password'             => Hash::make('Ofw12345!'),
            'is_ofw'               => true,
            'is_verified'          => true,
            'onboarding_completed' => true,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        DB::table('user_profiles')->insert([
            'user_id'          => $ofwId,
            'archetype'        => 'ofw_investor',
            'onboarding_step'  => 4,
            'family_size'      => 3,
            'num_children'     => 1,
            'children_ages'    => json_encode([10]),
            'purchase_purpose' => 'investment',
            'risk_tolerance'   => 'aggressive',
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
        DB::table('user_finances')->insert([
            'user_id'                       => $ofwId,
            'version'                       => 1,
            'is_current'                    => true,
            'gross_monthly_income_php'      => 120000.00,
            'currency'                      => 'USD',
            'employment_type'               => 'ofw',
            'has_co_borrower'               => false,
            'monthly_obligations_php'       => 0.00,
            'available_down_payment_php'    => 1500000.00,
            'monthly_savings_php'           => 40000.00,
            'pagibig_member'                => true,
            'pagibig_contributions_months'  => 72,
            'pagibig_contributions_current' => true,
            'created_at'                    => $now,
            'updated_at'                    => $now,
        ]);

        // ── Test Broker ────────────────────────────────────────────────────────
        $brokerId = (string) Str::uuid();
        DB::table('users')->insert([
            'id'                   => $brokerId,
            'name'                 => 'Carlos Tan',
            'email'                => 'broker@hanapbahay.ph',
            'email_verified_at'    => $now,
            'password'             => Hash::make('Broker123!'),
            'is_broker'            => true,
            'is_verified'          => true,
            'onboarding_completed' => true,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        DB::table('user_profiles')->insert([
            'user_id'    => $brokerId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('brokers')->insert([
            'user_id'            => $brokerId,
            'prc_license_no'     => 'PRC-B-2021-00451',
            'status'             => 'active',
            'license_expires_at' => now()->addYears(2),
            'specializations'    => json_encode(['condo', 'single_detached']),
            'avg_rating'         => 4.80,
            'total_reviews'      => 36,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);
    }
}
