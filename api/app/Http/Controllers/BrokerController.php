<?php

namespace App\Http\Controllers;

use App\Models\Broker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrokerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:50',
            'status'   => 'nullable|in:pending,verified,suspended',
        ]);

        $query = Broker::with('user:id,name,avatar_url')
            ->where('status', $validated['status'] ?? 'verified');

        return response()->json($query->paginate($validated['per_page'] ?? 20));
    }

    public function show(Broker $broker): JsonResponse
    {
        return response()->json(['data' => $broker->load('user:id,name,avatar_url')]);
    }

    public function apply(Request $request): JsonResponse
    {
        $user = $request->user();

        if (Broker::where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Application already submitted.'], 409);
        }

        $validated = $request->validate([
            'prc_license_no'      => 'required|string|max:50',
            'license_expires_at'  => 'required|date|after:today',
            'specializations'     => 'nullable|array',
            'specializations.*'   => 'string|max:50',
        ]);

        $broker = Broker::create([
            'user_id'            => $user->id,
            'prc_license_no'     => $validated['prc_license_no'],
            'license_expires_at' => $validated['license_expires_at'],
            'specializations'    => $validated['specializations'] ?? [],
            'status'             => 'pending',
        ]);

        return response()->json(['data' => $broker], 201);
    }

    public function verifyKyc(Request $request, Broker $broker): JsonResponse
    {
        $validated = $request->validate([
            'status'            => 'required|in:verified,suspended',
            'veriff_session_id' => 'nullable|string|max:100',
        ]);

        $broker->update([
            'status'            => $validated['status'],
            'veriff_session_id' => $validated['veriff_session_id'] ?? $broker->veriff_session_id,
        ]);

        return response()->json(['data' => $broker->fresh()]);
    }
}
