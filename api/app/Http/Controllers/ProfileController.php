<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile', 'currentFinances');
        return response()->json(['data' => $user]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'phone'      => 'sometimes|nullable|string|max:20',
            'is_ofw'     => 'sometimes|boolean',
            'locale'     => 'sometimes|string|max:10',
            'avatar_url' => 'sometimes|nullable|url|max:500',
        ]);

        $request->user()->update($validated);

        return response()->json(['data' => $request->user()->fresh()->load('profile')]);
    }

    public function completeOnboarding(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Step 1: Identity & Goal
            'purpose'             => 'required|in:residence,investment,both',
            'urgency'             => 'required|in:asap,6months,1year,exploring',
            // Step 2: Finances
            'monthly_gross'       => 'required|numeric|min:0',
            'monthly_takehome'    => 'required|numeric|min:0',
            'monthly_expenses'    => 'required|numeric|min:0',
            'existing_debts'      => 'required|numeric|min:0',
            'savings'             => 'required|numeric|min:0',
            'is_ofw'              => 'required|boolean',
            'target_amort_min'    => 'nullable|numeric|min:0',
            'target_amort_max'    => 'nullable|numeric|min:0',
            // Step 3: Household
            'family_size'         => 'required|integer|min:1|max:20',
            'commute_mode'        => 'required|in:car,transit,mixed',
            'max_commute_minutes' => 'required|integer|min:5|max:180',
            'needs_school'        => 'required|boolean',
            'needs_hospital'      => 'required|boolean',
            'work_city'           => 'required|string|max:100',
            // Step 4: Preferences
            'property_types'      => 'required|array|min:1',
            'property_types.*'    => 'in:condo,house_and_lot,townhouse,lot,apartment',
            'preferred_lgus'      => 'nullable|array',
            'risk_tolerance'      => 'required|in:conservative,balanced,aggressive',
            'must_haves'          => 'nullable|array',
            'deal_breakers'       => 'nullable|array',
        ]);

        $user = $request->user();

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'purchase_purpose' => $validated['purpose'],
                'risk_tolerance'   => $validated['risk_tolerance'],
                'family_size'      => $validated['family_size'],
            ]
        );

        $user->finances()->where('is_current', true)->update(['is_current' => false]);
        $user->finances()->create([
            'gross_monthly_income_php'   => $validated['monthly_gross'],
            'monthly_obligations_php'    => $validated['existing_debts'],
            'available_down_payment_php' => $validated['savings'],
            'monthly_savings_php'        => $validated['monthly_takehome'] - $validated['monthly_expenses'],
            'is_current'                 => true,
        ]);

        $user->update(['is_ofw' => $validated['is_ofw'], 'onboarding_completed' => true]);

        return response()->json(['data' => $user->fresh()->load('profile', 'currentFinances')]);
    }

    public function archetype(Request $request): JsonResponse
    {
        $profile = $request->user()->profile;

        return response()->json([
            'data' => [
                'archetype' => $profile?->archetype ?? 'unknown',
                'modifiers' => $profile?->modifiers ?? [],
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);

        if (!Hash::check($request->password, $request->user()->password)) {
            return response()->json(['message' => 'Password incorrect.'], 403);
        }

        $request->user()->delete();
        Auth::guard('web')->logout();
        $request->session()->invalidate();

        return response()->json(null, 204);
    }
}
