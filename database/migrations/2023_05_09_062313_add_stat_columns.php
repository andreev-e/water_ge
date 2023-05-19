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
        Schema::table('service_centers', static function(Blueprint $table) {
            $table->integer('total_addresses')->nullable();
            $table->integer('total_events')->nullable();
        });

        Schema::table('addresses', static function(Blueprint $table) {
            $table->integer('total_events')->nullable();
        });

        Schema::table('events', static function(Blueprint $table) {
            $table->integer('total_addresses')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_centers', static function(Blueprint $table) {
            $table->dropColumn(['total_addresses', 'total_events']);
        });

        Schema::table('addresses', static function(Blueprint $table) {
            $table->dropColumn('total_events');
        });

        Schema::table('events', static function(Blueprint $table) {
            $table->dropColumn('total_addresses');
        });
    }
};
