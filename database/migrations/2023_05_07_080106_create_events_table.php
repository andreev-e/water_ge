<?php

use App\Models\ServiceCenter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ServiceCenter::class);
            $table->dateTime('start');
            $table->dateTime('finish');
            $table->integer('effected_customers');
            $table->string('type');
            $table->text('name');
            $table->text('name_en');
            $table->text('name_ru');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
