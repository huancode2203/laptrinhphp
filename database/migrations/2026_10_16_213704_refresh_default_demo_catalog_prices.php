<?php

use App\DemoCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(DemoCatalog::class)->refreshPrices();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
