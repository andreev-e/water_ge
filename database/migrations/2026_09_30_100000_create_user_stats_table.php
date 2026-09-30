<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per day with the totals as they stood at the day's last
     * snapshot, so users and subscriptions deleted later still count there.
     */
    public function up(): void
    {
        Schema::create('user_stats', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->unsignedInteger('users');
            $table->unsignedInteger('subscriptions');
            $table->unsignedInteger('filtered');
            $table->timestamps();
        });

        // Deletions before today are lost, but the chart shouldn't start empty.
        Artisan::call('app:snapshot-user-stats', ['--backfill' => 30]);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_stats');
    }
};
