<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotent: a first prod run stopped midway (MySQL DDL is not transactional),
     * leaving some of these indexes in place.
     */
    public function up(): void
    {
        $this->addIndex('addresses', 'addresses_service_center_id_index', ['service_center_id']);
        $this->addIndex('events', 'events_service_center_id_index', ['service_center_id']);
        $this->addIndex('events', 'events_start_finish_index', ['start', 'finish']);
        $this->addIndex('subscriptions', 'subscriptions_bot_user_id_index', ['bot_user_id']);
        $this->addIndex('subscriptions', 'subscriptions_service_center_id_index', ['service_center_id']);
        $this->addIndex('address_event', 'address_event_address_id_event_id_unique', ['address_id', 'event_id'], true);
        $this->addIndex('address_event', 'address_event_event_id_index', ['event_id']);
    }

    private function addIndex(string $table, string $name, array $columns, bool $unique = false): void
    {
        if (Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($name, $columns, $unique) {
            $unique ? $table->unique($columns, $name) : $table->index($columns, $name);
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
