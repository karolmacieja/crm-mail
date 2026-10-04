<?php

namespace App\Http\Resources;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Reservation */
class ReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $startsAt = $this->startsAt($request->user()?->group?->timezone);

        return [
            'id' => $this->id,
            'contact_id' => $this->contact_id,
            // Author or the contact's owner; colleagues with access only view it.
            'can_edit' => $request->user() === null || $this->canBeChangedBy($request->user()),
            // Restaurant-local date/time as entered, plus an absolute ISO timestamp.
            'reservation_date' => $this->reservation_date->format('Y-m-d'),
            'reservation_time' => substr((string) $this->reservation_time, 0, 5),
            'starts_at' => $startsAt->toIso8601String(),
            'guests_count' => $this->guests_count,
            'status' => $this->status->value,
            'is_upcoming' => $startsAt->isFuture()
                && in_array($this->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true),
            'table_label' => $this->table_label,
            'occasion' => $this->occasion,
            'notes' => $this->notes,
            'source_email_id' => $this->source_email_id,
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? [
                'id' => $this->contact->id,
                'email' => $this->contact->email,
                'name' => $this->contact->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
