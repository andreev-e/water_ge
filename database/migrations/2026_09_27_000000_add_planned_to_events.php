<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Null means the source didn't tell: events loaded before this column.
     * Only GWP stored its text, and its emergency messages say "ავარიულად".
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('planned')->nullable();
        });

        DB::table('events')
            ->where('type', 'water')
            ->whereNotNull('external_id')
            ->where('name', 'LIKE', '%ავარი%')
            ->update(['planned' => false]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('planned');
        });
    }
};
