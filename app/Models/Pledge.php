<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pledge extends Model
{
    use HasFactory;

    protected $fillable = [
        'cause_id',
        'pledger_name',
        'pledger_email',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function cause(): BelongsTo
    {
        return $this->belongsTo(Cause::class);
    }
}