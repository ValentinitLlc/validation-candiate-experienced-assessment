<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pledges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cause_id')->constrained()->cascadeOnDelete();
            $table->string('pledger_name');
            $table->string('pledger_email');
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            // Index pour optimiser les requêtes d'agrégation et éviter les N+1
            $table->index('cause_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pledges');
    }
};