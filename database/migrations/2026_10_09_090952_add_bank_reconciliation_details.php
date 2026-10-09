<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_transactions', function (Blueprint $table): void {
            $table->string('account', 60)->nullable();
            $table->text('content')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('integration_transactions', fn (Blueprint $table) => $table->dropColumn(['account', 'content']));
    }
};
