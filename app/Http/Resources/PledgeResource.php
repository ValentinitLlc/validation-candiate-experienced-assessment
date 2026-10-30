<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PledgeResource extends JsonResource
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
            'cause_id' => $this->cause_id,
            'pledger_name' => $this->pledger_name,
            'pledger_email' => $this->pledger_email,
            'amount' => (float) $this->amount,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}