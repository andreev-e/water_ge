<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('events', 'facebook_post_id')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->string('facebook_post_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('facebook_post_id');
        });
    }
};
