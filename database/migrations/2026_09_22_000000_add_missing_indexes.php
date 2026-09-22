<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->index('service_center_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->index('service_center_id');
            $table->index(['start', 'finish']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index('bot_user_id');
            $table->index('service_center_id');
        });

        Schema::table('address_event', function (Blueprint $table) {
            $table->unique(['address_id', 'event_id']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex(['service_center_id']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['service_center_id']);
            $table->dropIndex(['start', 'finish']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['bot_user_id']);
            $table->dropIndex(['service_center_id']);
        });

        Schema::table('address_event', function (Blueprint $table) {
            $table->dropUnique(['address_id', 'event_id']);
            $table->dropIndex(['event_id']);
        });
    }
};
