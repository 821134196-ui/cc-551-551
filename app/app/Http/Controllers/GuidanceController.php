<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\GuidanceSession;
use App\Services\GuidanceService;
use Illuminate\Http\Request;

class GuidanceController extends Controller
{
    use ChecksOwnership;

    public function show(Request $request, GuidanceSession $guidance)
    {
        $this->assertCanAccessSession($guidance);
        $guidance->load([
            'appointment.timeSlot', 'submission', 'advisor', 'student',
            'feedbackItems.responses.submission', 'attachments',
        ]);

        return response()->json(['data' => $this->present($guidance)]);
    }

    public function store(Request $request, Appointment $appointment, GuidanceService $service)
    {
        $data = $request->validate([
            'submission_id' => ['required', 'integer', 'exists:submissions,id'],
            'overall_comment' => ['required', 'string', 'max:5000'],
            'feedback_items' => ['required', 'array', 'min:1'],
            'feedback_items.*' => ['string', 'max:2000'],
            'files' => ['array', 'max:10'],
            'files.*' => ['file', 'max:20480'],
        ]);

        $session = $service->record(
            $request->user(),
            $appointment,
            $data,
            $request->file('files', []),
        );

        return response()->json(['data' => $this->present($session)], 201);
    }

    private function present(GuidanceSession $g): array
    {
        return [
            'id' => $g->id,
            'overall_comment' => $g->overall_comment,
            'created_at' => $g->created_at->toIso8601String(),
            'submission' => $g->relationLoaded('submission') ? [
                'id' => $g->submission->id,
                'version' => $g->submission->version,
                'title' => $g->submission->title,
                'status' => $g->submission->status,
            ] : null,
            'appointment' => $g->relationLoaded('appointment') ? [
                'id' => $g->appointment->id,
                'time_slot' => $g->appointment->relationLoaded('timeSlot') && $g->appointment->timeSlot ? [
                    'start_at' => $g->appointment->timeSlot->start_at->toIso8601String(),
                    'location' => $g->appointment->timeSlot->location,
                ] : null,
            ] : null,
            'student' => $g->relationLoaded('student') ? ['id' => $g->student->id, 'name' => $g->student->name] : null,
            'feedback_items' => $g->relationLoaded('feedbackItems') ? $g->feedbackItems
                ->sortBy('sequence')
                ->map(fn ($f) => [
                    'id' => $f->id,
                    'sequence' => $f->sequence,
                    'content' => $f->content,
                    'status' => $f->status,
                    'responses' => $f->relationLoaded('responses') ? $f->responses->map(fn ($r) => [
                        'id' => $r->id,
                        'reply' => $r->reply,
                        'created_at' => $r->created_at->toIso8601String(),
                        'submission_version' => $r->relationLoaded('submission') && $r->submission
                            ? $r->submission->version : null,
                    ]) : [],
                ]) : [],
            'attachments' => $g->relationLoaded('attachments') ? $g->attachments->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->original_name,
                'size' => $a->size,
                'download_url' => url("/api/attachments/{$a->id}/download"),
            ]) : [],
        ];
    }
}
