<?php

use App\Models\ServiceCenter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service center pages move from /?service_center_id=5 to /service-centers/kutaisi.
 * Older centers come first, so of the duplicates the original keeps the plain slug.
 * Names that machine translation got wrong are fixed first, so they don't
 * end up in the URLs too.
 */
return new class extends Migration
{
    private const NAMES = [
        // Was "Новичок" / "Newcomer".
        'ახალგორი' => ['name_ru' => 'Ахалгори', 'name_en' => 'Akhalgori'],
        'ხარაგაულის სერვის ცენტრი' => ['name_ru' => 'Харагаули'],
    ];

    public function up(): void
    {
        Schema::table('service_centers', static function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name_ru');
        });

        foreach (self::NAMES as $name => $names) {
            DB::table('service_centers')->where('name', $name)->update($names);
        }

        ServiceCenter::query()->orderBy('id')->each(function (ServiceCenter $serviceCenter) {
            DB::table('service_centers')
                ->where('id', $serviceCenter->id)
                ->update(['slug' => ServiceCenter::uniqueSlug($serviceCenter->name_ru ?: $serviceCenter->name)]);
        });
    }

    public function down(): void
    {
        Schema::table('service_centers', static function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
