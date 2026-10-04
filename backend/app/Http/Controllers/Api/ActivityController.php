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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Contact timeline ("Oś czasu / Historia"): list, quick notes, logged emails.
 */
class ActivityController extends Controller
{
    private const THREAD_PLACEHOLDER = 'thread:';

    public function index(Request $request, Contact $contact): AnonymousResourceCollection
    {
        $access = ContactController::authorizeView($request->user(), $contact);
        $validated = $request->validate([
            'type' => ['nullable', Rule::enum(ActivityType::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $timeline = $contact->isPersonal()
            ? Activity::query()->forPersonalCard($contact)
            : $contact->timeline()->visibleWith($access, $request->user());

        return ActivityResource::collection(
            $timeline
                ->with(['author:id,name', 'sharedBy:id,name'])
                ->ofType($validated['type'] ?? null)
                ->paginate($validated['per_page'] ?? 20)
                ->withQueryString()
        );
    }

    /** "Zapisz do osi czasu" – a manual note. */
    public function storeNote(Request $request, Contact $contact): JsonResponse
    {
        ContactController::authorizeView($request->user(), $contact);
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
        ContactController::authorizeView($request->user(), $contact);
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

    /**
     * Import past Gmail correspondence of a contact (read by the extension
     * through the Gmail API). Duplicates are skipped; inbox placeholders
     * ("thread:<id>") are upgraded to the real message.
     */
    public function importEmails(Request $request, Contact $contact): JsonResponse
    {
        ContactController::authorizeView($request->user(), $contact);
        $data = $request->validate([
            'emails' => ['present', 'array', 'max:500'],
            'emails.*.message_id' => ['required', 'string', 'max:255'],
            'emails.*.thread_id' => ['nullable', 'string', 'max:255'],
            'emails.*.subject' => ['nullable', 'string', 'max:255'],
            'emails.*.snippet' => ['nullable', 'string', 'max:2000'],
            'emails.*.from' => ['nullable', 'string', 'max:255'],
            'emails.*.direction' => ['sometimes', 'in:in,out'],
            'emails.*.sent_at' => ['nullable', 'date'],
            'complete' => ['sometimes', 'boolean'],
        ]);

        $existing = $contact->timeline()->where('type', ActivityType::Email)->get(['id', 'meta']);
        $known = $existing->map(fn (Activity $a) => $a->meta['message_id'] ?? null)->filter()->flip();
        $placeholders = $existing->filter(fn (Activity $a) => str_starts_with($a->meta['message_id'] ?? '', self::THREAD_PLACEHOLDER))
            ->keyBy(fn (Activity $a) => substr($a->meta['message_id'], strlen(self::THREAD_PLACEHOLDER)));

        $imported = $upgraded = $skipped = 0;

        DB::transaction(function () use ($data, $contact, $request, $known, $placeholders, &$imported, &$upgraded, &$skipped) {
            foreach ($data['emails'] as $email) {
                if ($known->has($email['message_id'])) {
                    $skipped++;

                    continue;
                }

                $placeholder = isset($email['thread_id']) ? $placeholders->pull($email['thread_id']) : null;
                if ($placeholder !== null) {
                    $placeholder->update([
                        'title' => $email['subject'] ?? $placeholder->title,
                        'body' => $email['snippet'] ?? $placeholder->body,
                        'occurred_at' => isset($email['sent_at']) ? Carbon::parse($email['sent_at']) : $placeholder->occurred_at,
                        'meta' => array_merge($placeholder->meta ?? [], array_filter([
                            'message_id' => $email['message_id'],
                            'from' => $email['from'] ?? null,
                            'direction' => $email['direction'] ?? null,
                        ])),
                    ]);
                    $upgraded++;
                } else {
                    Activity::recordEmail($contact, $email, $request->user());
                    $imported++;
                }
                $known->put($email['message_id'], true);
            }

            // The extension sends the history in chunks; the last one marks it complete.
            if ($data['complete'] ?? true) {
                $contact->forceFill(['email_history_synced_at' => now()])->save();
            }
        });

        return response()->json(['data' => [
            'imported' => $imported,
            'updated' => $upgraded,
            'skipped' => $skipped,
            'email_history_synced_at' => $contact->fresh()->email_history_synced_at?->toIso8601String(),
        ]]);
    }

    /** Edit your own note (shown under "Dane kontaktowe" and on the timeline). */
    public function update(Request $request, Contact $contact, Activity $activity): ActivityResource
    {
        $this->authorizeOwnNote($request, $contact, $activity);
        $activity->update($request->validate(['body' => ['required', 'string', 'max:10000']]));

        return new ActivityResource($activity->load('author:id,name'));
    }

    public function destroy(Request $request, Contact $contact, Activity $activity): JsonResponse
    {
        $this->authorizeOwnNote($request, $contact, $activity);
        $activity->delete();

        return response()->json(null, 204);
    }

    /**
     * Business correspondence: add one email from my personal card to the
     * team pool – colleagues with their own card of this contact see it.
     */
    public function teamShare(Request $request, Contact $contact, Activity $activity): ActivityResource
    {
        $this->authorizePoolEmail($request, $contact, $activity);
        $activity->forceFill(['team_shared_at' => now(), 'team_shared_by' => $request->user()->id])->save();

        return new ActivityResource($activity->load(['author:id,name', 'sharedBy:id,name']));
    }

    public function teamUnshare(Request $request, Contact $contact, Activity $activity): ActivityResource
    {
        $this->authorizePoolEmail($request, $contact, $activity);
        $activity->forceFill(['team_shared_at' => null, 'team_shared_by' => null])->save();

        return new ActivityResource($activity->load('author:id,name'));
    }

    private function authorizePoolEmail(Request $request, Contact $contact, Activity $activity): void
    {
        ContactController::authorizeManage($request->user(), $contact);
        abort_unless($activity->contact_id === $contact->id && $activity->type === ActivityType::Email, 404);
        abort_unless($contact->isPersonal(), 422, __('crm.personal.pool_only_personal'));
    }

    /** Only your own notes can be changed; emails and system events are history. */
    private function authorizeOwnNote(Request $request, Contact $contact, Activity $activity): void
    {
        ContactController::authorizeView($request->user(), $contact);
        abort_unless(
            $activity->contact_id === $contact->id
                && $activity->type === ActivityType::Note
                && $activity->user_id === $request->user()->id,
            403,
        );
    }
}
