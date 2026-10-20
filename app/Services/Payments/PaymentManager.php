<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class PaymentManager
{
    /**
     * Resolve the requested payment gateway implementation.
     */
    public function gateway(string $driver): PaymentGatewayInterface
    {
        return match ($driver) {
            'mpesa' => app(MpesaPaymentGateway::class),
            'cash' => app(CashPaymentGateway::class),
            'stripe', 'card' => app(StripePaymentGateway::class),
            'paypal' => app(PayPalPaymentGateway::class),
            default => throw new InvalidArgumentException("Unsupported payment gateway: {$driver}"),
        };
    }
}
