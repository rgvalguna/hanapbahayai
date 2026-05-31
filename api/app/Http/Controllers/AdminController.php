<?php

namespace App\Http\Controllers;

use App\Models\Consultation;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AdminController extends Controller
{
    public function pendingListings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $listings = Listing::where('status', 'pending')
            ->with('developer', 'broker')
            ->orderBy('created_at')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json($listings);
    }

    public function approveListing(Request $request, Listing $listing): JsonResponse
    {
        abort_if($listing->status !== 'pending', 422, 'Listing is not in pending state.');

        $listing->update(['status' => 'live']);

        return response()->json(['data' => $listing->fresh()]);
    }

    public function rejectListing(Request $request, Listing $listing): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        abort_if($listing->status !== 'pending', 422, 'Listing is not in pending state.');

        $listing->update([
            'status'      => 'rejected',
            'fraud_flags' => array_merge(
                $listing->fraud_flags ?? [],
                $validated['reason'] ? [['type' => 'admin_rejection', 'note' => $validated['reason']]] : []
            ),
        ]);

        return response()->json(null, 204);
    }

    public function users(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'q'        => 'nullable|string|max:100',
        ]);

        $query = User::with('profile')
            ->orderByDesc('created_at');

        if (!empty($validated['q'])) {
            $q = $validated['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        return response()->json($query->paginate($validated['per_page'] ?? 50));
    }

    public function metrics(): JsonResponse
    {
        $listings = DB::table('listings')
            ->selectRaw("status, COUNT(*) as count")
            ->groupBy('status')
            ->pluck('count', 'status');

        $users = DB::table('users')
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN onboarding_completed THEN 1 END) as onboarded,
                COUNT(CASE WHEN created_at >= NOW() - INTERVAL '30 days' THEN 1 END) as last_30_days
            ")
            ->first();

        $consultations = DB::table('consultations')
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN created_at >= NOW() - INTERVAL '30 days' THEN 1 END) as last_30_days
            ")
            ->first();

        $brokers = DB::table('brokers')
            ->selectRaw("status, COUNT(*) as count")
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'data' => [
                'listings'      => $listings,
                'users'         => $users,
                'consultations' => $consultations,
                'brokers'       => $brokers,
            ],
        ]);
    }

    public function triggerIngestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source'   => 'required|string|in:pagibig,bank_ropa,partner_feed',
            'backfill' => 'nullable|boolean',
        ]);

        $ingestionUrl = config('services.ingestion.url');

        if (!$ingestionUrl) {
            return response()->json(['message' => 'Ingestion service not configured.'], 503);
        }

        $response = Http::timeout(10)
            ->post("{$ingestionUrl}/trigger", [
                'source'   => $validated['source'],
                'backfill' => $validated['backfill'] ?? false,
                'triggered_by' => $request->user()->id,
            ]);

        if ($response->failed()) {
            return response()->json(['message' => 'Ingestion trigger failed.'], 502);
        }

        return response()->json(['data' => $response->json()]);
    }
}
