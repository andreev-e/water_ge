<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outages that are already over count as notified, otherwise the first
     * run would report the end of the whole archive.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('finish_notified')->default(false)->index();
        });

        DB::table('events')
            ->where('finish', '<=', now())
            ->update(['finish_notified' => true]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('finish_notified');
        });
    }
};
