<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Shortlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shortlists = Shortlist::where('user_id', $request->user()->id)
            ->with(['listings' => fn ($q) => $q->select('listings.id', 'title', 'price_php', 'property_type', 'address')])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $shortlists]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            Shortlist::where('user_id', $request->user()->id)
                ->update(['is_default' => false]);
        }

        $shortlist = Shortlist::create([
            'user_id'    => $request->user()->id,
            'name'       => $validated['name'],
            'is_default' => $validated['is_default'] ?? false,
        ]);

        return response()->json(['data' => $shortlist], 201);
    }

    public function update(Request $request, Shortlist $shortlist): JsonResponse
    {
        abort_if($shortlist->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'name'       => 'sometimes|string|max:100',
            'is_default' => 'sometimes|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            Shortlist::where('user_id', $request->user()->id)
                ->where('id', '!=', $shortlist->id)
                ->update(['is_default' => false]);
        }

        $shortlist->update($validated);

        return response()->json(['data' => $shortlist->fresh()]);
    }

    public function destroy(Request $request, Shortlist $shortlist): JsonResponse
    {
        abort_if($shortlist->user_id !== $request->user()->id, 403);

        $shortlist->delete();

        return response()->json(null, 204);
    }

    public function addListing(Request $request, Shortlist $shortlist, Listing $listing): JsonResponse
    {
        abort_if($shortlist->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $shortlist->listings()->syncWithoutDetaching([
            $listing->id => ['note' => $validated['note'] ?? null],
        ]);

        return response()->json(['data' => $shortlist->load('listings')]);
    }

    public function removeListing(Request $request, Shortlist $shortlist, Listing $listing): JsonResponse
    {
        abort_if($shortlist->user_id !== $request->user()->id, 403);

        $shortlist->listings()->detach($listing->id);

        return response()->json(null, 204);
    }
}
