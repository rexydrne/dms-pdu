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
        Schema::create('file_labels', function (Blueprint $table) {
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->string('label_id', 255);
            $table->timestamps();

            $table->primary(['file_id', 'label_id']);
            $table->foreign('label_id')->references('id')->on('labels')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_labels');
    }
};
