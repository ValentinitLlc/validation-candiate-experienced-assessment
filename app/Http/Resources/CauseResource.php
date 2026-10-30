<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CauseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'goal_amount' => $this->goal_amount,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'total_pledged' => $this->whenLoaded('pledges', fn() => $this->pledges->sum('amount'), $this->total_pledged ?? 0),
            'pledge_count' => $this->whenLoaded('pledges', fn() => $this->pledges->count(), $this->pledge_count ?? 0),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
