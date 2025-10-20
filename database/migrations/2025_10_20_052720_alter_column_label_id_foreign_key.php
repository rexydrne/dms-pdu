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
        Schema::table('file_labels', function (Blueprint $table) {
            $table->dropForeign('file_labels_label_id_foreign');
        });

        Schema::table('file_labels', function (Blueprint $table) {
            $table->bigInteger('label_id')->unsigned()->change();
        });

        Schema::table('labels', function (Blueprint $table) {
            $table->id()->change();
        });

        Schema::table('file_labels', function (Blueprint $table) {
            $table->foreign('label_id')
                  ->references('id')->on('labels')
                  ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('file_labels', function (Blueprint $table) {
            $table->dropForeign(['label_id']);
        });

        Schema::table('labels', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement()->change();
        });

        Schema::table('file_labels', function (Blueprint $table) {
            $table->unsignedInteger('label_id')->change();
        });

        Schema::table('file_labels', function (Blueprint $table) {
            $table->foreign('label_id')
                  ->references('id')->on('labels');
        });
    }
};
