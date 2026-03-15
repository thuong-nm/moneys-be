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
        Schema::table('text_shares', function (Blueprint $table) {
            $table->string('browser_id', 64)->nullable()->index()->after('hash_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('text_shares', function (Blueprint $table) {
            $table->dropColumn('browser_id');
        });
    }
};
