<?php

namespace Tests;

use App\Models\PaymentAttempt;
use App\PayosService;
use Illuminate\Support\Facades\Http;

trait FakesBankCheckout
{
    protected function fakeBankCheckout(): void
    {
        config(['services.payos' => ['client_id' => 'test-client', 'api_key' => 'test-key', 'checksum_key' => 'test-checksum']]);
        Http::fake(['https://api-merchant.payos.vn/v2/payment-requests' => function ($request) {
            $data = ['orderCode' => $request['orderCode'], 'amount' => $request['amount'], 'currency' => 'VND', 'paymentLinkId' => 'bank-test-'.$request['orderCode'], 'checkoutUrl' => 'https://pay.payos.vn/web/bank-test-'.$request['orderCode']];

            return Http::response(['code' => '00', 'data' => $data, 'signature' => app(PayosService::class)->sign($data)]);
        }]);
    }

    protected function settleBankCheckout(PaymentAttempt $attempt): void
    {
        $extra = json_decode(base64_decode($attempt->extra_data), true);
        $status = ['id' => $extra['payment_link_id'], 'orderCode' => (int) $attempt->order_id, 'amount' => $attempt->amount, 'amountPaid' => $attempt->amount, 'amountRemaining' => 0, 'status' => 'PAID', 'transactions' => []];
        Http::fake(['https://api-merchant.payos.vn/v2/payment-requests/'.$attempt->order_id => Http::response(['code' => '00', 'data' => $status, 'signature' => app(PayosService::class)->sign($status)])]);
        $data = ['orderCode' => (int) $attempt->order_id, 'amount' => $attempt->amount, 'currency' => 'VND', 'paymentLinkId' => $extra['payment_link_id'], 'code' => '00', 'reference' => 'test-bank-'.$attempt->order_id];
        $this->postJson('/api/v1/payments/payos/webhook', ['code' => '00', 'success' => true, 'data' => $data, 'signature' => app(PayosService::class)->sign($data)])->assertOk();
    }
}
