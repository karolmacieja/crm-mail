<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmailActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Contact timeline ("Oś czasu / Historia"): list, quick notes, logged emails.
 */
class ActivityController extends Controller
{
    private const THREAD_PLACEHOLDER = 'thread:';

    public function index(Request $request, Contact $contact): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::enum(ActivityType::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return ActivityResource::collection(
            $contact->timeline()
                ->with('author:id,name')
                ->ofType($validated['type'] ?? null)
                ->paginate($validated['per_page'] ?? 20)
                ->withQueryString()
        );
    }

    /** "Zapisz do osi czasu" – a manual note. */
    public function storeNote(Request $request, Contact $contact): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $note = $contact->addNote($validated['body'], $request->user());

        return (new ActivityResource($note->load('author:id,name')))->response()->setStatusCode(201);
    }

    /**
     * Log a Gmail message on the timeline. Idempotent per Gmail message id,
     * so the extension can call it every time the email is opened.
     */
    public function storeEmail(StoreEmailActivityRequest $request, Contact $contact): JsonResponse
    {
        $messageId = $request->validated('message_id');
        $threadId = $request->validated('thread_id');
        $emails = fn () => $contact->timeline()->where('type', ActivityType::Email);

        // 1. Same Gmail message already logged.
        if ($existing = $emails()->where('meta->message_id', $messageId)->first()) {
            return (new ActivityResource($existing))->response()->setStatusCode(200);
        }

        // Gmail's inbox list only exposes thread ids, so the dashboard logs
        // "thread:<id>" placeholders; the thread view knows the real message id.
        $isPlaceholder = str_starts_with($messageId, self::THREAD_PLACEHOLDER);

        if ($threadId !== null) {
            // 2. A placeholder for a thread that is already on the timeline adds nothing.
            if ($isPlaceholder && ($existing = $emails()->where('meta->thread_id', $threadId)->first())) {
                return (new ActivityResource($existing))->response()->setStatusCode(200);
            }

            // 3. The real message replaces the thread placeholder instead of duplicating it.
            $placeholder = $isPlaceholder ? null : $emails()->where('meta->message_id', self::THREAD_PLACEHOLDER.$threadId)->first();
            if ($placeholder !== null) {
                $placeholder->update([
                    'title' => $request->validated('subject') ?? $placeholder->title,
                    'body' => $request->validated('snippet') ?? $placeholder->body,
                    'meta' => array_merge($placeholder->meta ?? [], array_filter([
                        'message_id' => $messageId,
                        'from' => $request->validated('from'),
                        'direction' => $request->validated('direction'),
                    ])),
                ]);

                return (new ActivityResource($placeholder))->response()->setStatusCode(200);
            }
        }

        $activity = Activity::recordEmail($contact, $request->validated(), $request->user());

        return (new ActivityResource($activity))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Contact $contact, Activity $activity): JsonResponse
    {
        // Only your own notes can be deleted; emails and system events are history.
        abort_unless(
            $activity->contact_id === $contact->id
                && $activity->type === ActivityType::Note
                && $activity->user_id === $request->user()->id,
            403,
        );

        $activity->delete();

        return response()->json(null, 204);
    }
}
