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
        Schema::table('shareables', function (Blueprint $table) {
            $table->dropForeign(['permission_id']);
            $table->dropColumn('permission_id');
            $table->foreignId('role_id')->constrained('roles')->onDelete('restrict')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shareables', function (Blueprint $table) {
            $table->foreignId('permission_id')
                ->constrained('permissions')
                ->onDelete('restrict')
                ->onUpdate('cascade');
        });
    }
};
