<?php

use App\Models\Address;
use App\Models\Event;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('address_event', function (Blueprint $table) {
            $table->foreignIdFor(Address::class);
            $table->foreignIdFor(Event::class);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses_events');
    }
};
