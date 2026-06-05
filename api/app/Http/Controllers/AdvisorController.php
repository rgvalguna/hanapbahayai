<?php

namespace App\Http\Controllers;

use App\Models\Consultation;
use App\Models\Listing;
use App\Modules\AI\ClaudeOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdvisorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $consultations = Consultation::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($consultations);
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
        ]);

        $consultation = Consultation::create([
            'user_id' => $request->user()->id,
            'title'   => $validated['title'] ?? 'New Consultation',
            'model'   => 'claude-opus-4-7',
        ]);

        return response()->json(['data' => $consultation], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $consultation = Consultation::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with(['messages', 'recommendations.listing'])
            ->firstOrFail();

        return response()->json(['data' => $consultation]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $consultation = Consultation::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $consultation->delete();

        return response()->json(null, 204);
    }

    public function sendMessage(Request $request, string $id): StreamedResponse
    {
        $consultation = Consultation::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'message'     => 'required|string|max:4000',
            'listing_ids' => 'nullable|array|max:10',
            'listing_ids.*' => 'uuid',
        ]);

        // Persist the user turn immediately
        $consultation->messages()->create([
            'role'    => 'user',
            'content' => $validated['message'],
        ]);

        $user     = $request->user()->load('profile', 'currentFinances');
        $listings = [];
        if (!empty($validated['listing_ids'])) {
            $listings = Listing::whereIn('id', $validated['listing_ids'])
                ->where('status', 'active')
                ->with('developer', 'broker')
                ->get()
                ->toArray();
        }

        return response()->stream(
            function () use ($consultation, $user, $listings) {
                try {
                    $orchestrator = new ClaudeOrchestrator();
                    $orchestrator->stream(
                        $consultation,
                        $user,
                        $listings,
                        function (array $event) {
                            echo 'data: ' . json_encode($event) . "\n\n";
                            ob_flush();
                            flush();
                        }
                    );
                } catch (\Throwable $e) {
                    echo 'data: ' . json_encode([
                        'type'    => 'error',
                        'message' => 'The advisor encountered an error. Please try again.',
                    ]) . "\n\n";
                    ob_flush();
                    flush();

                    echo 'data: ' . json_encode(['type' => 'done']) . "\n\n";
                    ob_flush();
                    flush();
                }
            },
            200,
            [
                'Content-Type'    => 'text/event-stream',
                'Cache-Control'   => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ]
        );
    }

    public function recommendations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $recommendations = \App\Models\Recommendation::whereHas(
            'consultation',
            fn ($q) => $q->where('user_id', $request->user()->id)
        )
            ->with('listing', 'consultation')
            ->orderByDesc('created_at')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json($recommendations);
    }
}
