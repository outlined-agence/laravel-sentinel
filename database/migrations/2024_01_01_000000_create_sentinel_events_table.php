<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Get the table name from config or use default.
     */
    protected function getTableName(): string
    {
        return config('sentinel.database.table', 'sentinel_events');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create($this->getTableName(), function (Blueprint $table) {
            $table->id();
            $table->string('level', 20)->index();
            $table->text('message');
            $table->string('event_type', 100)->nullable()->index();
            $table->json('context')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('url')->nullable();
            $table->string('environment', 50)->nullable()->index();
            $table->timestamps();

            // Composite index for common queries
            $table->index(['level', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index(['environment', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->getTableName());
    }
};
