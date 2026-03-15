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
        // Drop old feature tables (users, devices, subscriptions, auth)
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to recreate - these are being permanently removed
    }
};
