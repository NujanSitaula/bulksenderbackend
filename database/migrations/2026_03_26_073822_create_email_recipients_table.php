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
        Schema::create('email_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_batch_id')->constrained('email_batches')->cascadeOnDelete();
            $table->string('email');
            $table->string('unsubscribe_token', 64)->unique();
            $table->string('open_token', 64)->unique();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->string('send_status')->nullable();
            $table->text('send_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_recipients');
    }
};
