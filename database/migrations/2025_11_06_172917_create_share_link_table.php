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
        Schema::create('share_link', function (Blueprint $table) {
            $table->id();
            $table->string('token', 12)->unique();
            $table->string('path')->index();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('restrict')->onUpdate('cascade');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('share_link');
    }
};
