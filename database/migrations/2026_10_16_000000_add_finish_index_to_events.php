<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Current events (Event::scopeCurrent) are found by finish >= now: the (start, finish)
     * index is useless there because start <= now + week matches the whole archive,
     * so the home page read all ~140k events (0.5–0.7 s) to return ~230.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->index('finish');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['finish']);
        });
    }
};
