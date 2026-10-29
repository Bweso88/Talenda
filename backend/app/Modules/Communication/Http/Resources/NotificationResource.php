<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Resources;

use App\Modules\Communication\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserNotification
 */
final class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'title' => $this->title,
            'body' => $this->body,
            // child (uuid de l'enfant), tab (finances, results, attendance), result, announcement.
            'data' => (object) $this->data,
            'read' => $this->read_at !== null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
