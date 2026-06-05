<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q'             => 'nullable|string|max:200',
            'city'          => 'nullable|string|max:100',
            'property_type' => 'nullable|in:condo,townhouse,single_detached,lot_only,commercial,warehouse',
            'min_price'     => 'nullable|numeric|min:0',
            'max_price'     => 'nullable|numeric|min:0',
            'min_bedrooms'  => 'nullable|integer|min:0',
            'max_bedrooms'  => 'nullable|integer|min:0',
            'lat'           => 'nullable|numeric|between:-90,90',
            'lng'           => 'nullable|numeric|between:-180,180',
            'radius_km'     => 'nullable|numeric|min:0.1|max:100',
            'per_page'      => 'nullable|integer|min:1|max:50',
            'page'          => 'nullable|integer|min:1',
        ]);

        if (!empty($validated['q'])) {
            $listings = Listing::search($validated['q'])
                ->query(fn ($q) => $q->where('status', 'active')->with('developer', 'broker'))
                ->paginate($validated['per_page'] ?? 20);
        } else {
            $query = Listing::where('status', 'active')->with('developer', 'broker');

            if (!empty($validated['property_type'])) {
                $query->where('property_type', $validated['property_type']);
            }
            if (!empty($validated['min_price'])) {
                $query->where('price_php', '>=', $validated['min_price']);
            }
            if (!empty($validated['max_price'])) {
                $query->where('price_php', '<=', $validated['max_price']);
            }
            if (isset($validated['min_bedrooms'])) {
                $query->where('bedrooms', '>=', $validated['min_bedrooms']);
            }

            $listings = $query->paginate($validated['per_page'] ?? 20);
        }

        return response()->json($listings);
    }

    public function show(Listing $listing): JsonResponse
    {
        return response()->json(['data' => $listing->load('developer', 'broker')]);
    }

    public function score(Request $request, Listing $listing): JsonResponse
    {
        $score = $listing->score_cache ?? [
            'total'        => 0,
            'affordability'=> 0,
            'commute'      => 0,
            'safety'       => 0,
            'flood'        => 0,
            'education'    => 0,
            'healthcare'   => 0,
            'internet'     => 0,
            'investment'   => 0,
            'developer'    => 0,
            'livability'   => 0,
            'rationale'    => 'Score pending computation.',
            'warnings'     => [],
        ];

        return response()->json(['data' => $score]);
    }

    public function similar(Listing $listing): JsonResponse
    {
        $similar = Listing::where('status', 'active')
            ->where('property_type', $listing->property_type)
            ->where('id', '!=', $listing->id)
            ->whereBetween('price_php', [
                (float) $listing->price_php * 0.8,
                (float) $listing->price_php * 1.2,
            ])
            ->limit(6)
            ->get();

        return response()->json(['data' => $similar]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'          => 'required|string|max:500',
            'description'    => 'nullable|string|max:5000',
            'price_php'      => 'required|numeric|min:0',
            'property_type'  => 'required|in:condo,house_and_lot,townhouse,lot,apartment',
            'bedrooms'       => 'nullable|integer|min:0',
            'bathrooms'      => 'nullable|integer|min:0',
            'floor_area_sqm' => 'nullable|numeric|min:0',
            'lot_area_sqm'   => 'nullable|numeric|min:0',
            'address'        => 'required|array',
            'address.region' => 'required|string',
            'address.city'   => 'required|string',
            'photos'         => 'nullable|array',
            'photos.*'       => 'url',
        ]);

        $listing = Listing::create([
            ...$validated,
            'status'    => 'pending',
            'source'    => 'broker_upload',
        ]);

        return response()->json(['data' => $listing], 201);
    }

    public function update(Request $request, Listing $listing): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'sometimes|string|max:500',
            'description' => 'sometimes|nullable|string|max:5000',
            'price_php'   => 'sometimes|numeric|min:0',
            'status'      => 'sometimes|in:live,withdrawn',
            'photos'      => 'sometimes|array',
            'photos.*'    => 'url',
        ]);

        $listing->update($validated);

        return response()->json(['data' => $listing->fresh()]);
    }

    public function destroy(Listing $listing): JsonResponse
    {
        $listing->delete();
        return response()->json(null, 204);
    }
}
