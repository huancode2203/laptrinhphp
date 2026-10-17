<?php

use App\AccountIdentity;
use App\Models\Payment;
use App\PaymentAllocation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('phone_normalized', 20)->nullable()->unique());
        Schema::table('payments', fn (Blueprint $table) => $table->json('allocation')->nullable());
        Schema::table('bookings', fn (Blueprint $table) => $table->timestamp('checkout_due_at')->nullable());
        $phones = DB::table('users')->whereNotNull('phone')->get(['id', 'phone'])->groupBy(fn ($user) => AccountIdentity::phone($user->phone));
        foreach ($phones as $phone => $users) {
            if ($users->count() === 1 && preg_match('/^\+84(?:[35789][0-9]{8}|2[0-9]{9})$/D', $phone)) {
                DB::table('users')->where('id', $users->first()->id)->update(['phone' => $phone, 'phone_normalized' => $phone]);
            }
        }
        Payment::orderBy('id')->chunkById(100, function ($payments): void {
            foreach ($payments as $payment) {
                $snapshot = app(PaymentAllocation::class)->capture($payment->booking, (int) $payment->amount, $payment->kind, $payment->id, true);
                DB::table('payments')->where('id', $payment->id)->update(['allocation' => json_encode($snapshot, JSON_UNESCAPED_UNICODE)]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['phone_normalized']);
            $table->dropColumn('phone_normalized');
        });
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('allocation'));
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('checkout_due_at'));
    }
};
