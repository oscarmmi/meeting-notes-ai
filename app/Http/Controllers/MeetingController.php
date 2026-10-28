<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Services\Notes\InvalidMinutesResponse;
use App\Services\Notes\MinutesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MeetingController extends Controller
{
    public function index(): Response
    {
        $meetings = Meeting::query()
            ->with('actionItems')
            ->latest()
            ->get();

        return Inertia::render('Welcome', [
            'meetings' => $meetings,
            'llmMode' => filled(config('services.gemini.api_key'))
                ? 'Gemini connected'
                : 'Offline mode',
        ]);
    }

    public function store(Request $request, MinutesService $minutesService): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:180'],
            'raw_transcript' => ['required', 'string', 'min:1', 'max:50000'],
            'attendees' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $meeting = $minutesService->createMeeting([
                'title' => trim($validated['title'] ?? '') ?: 'Untitled meeting',
                'raw_transcript' => $validated['raw_transcript'],
                'attendees' => collect(explode(',', $validated['attendees'] ?? ''))
                    ->map(fn (string $attendee): string => trim($attendee))
                    ->filter()
                    ->values()
                    ->all(),
            ]);
        } catch (InvalidMinutesResponse) {
            return response()->json([
                'message' => 'We could not read the generated minutes. Please try again or check the transcript.',
            ], 422);
        } catch (Throwable $exception) {
            Log::warning('Meeting notes generation failed.', ['exception' => $exception]);

            return response()->json([
                'message' => 'Meeting notes are temporarily unavailable. Your transcript was not saved; please try again.',
            ], 503);
        }

        return response()->json(['meeting' => $meeting], 200);
    }
}
