<?php

namespace App\Services\Payments;

class PaymentResult
{
    public function __construct(
        public bool $success,
        public string $status, // pending, successful, failed
        public ?string $reference = null,
        public ?string $message = null,
        public array $data = []
    ) {}

    public static function pending(?string $reference = null, ?string $message = null, array $data = []): self
    {
        return new self(true, 'pending', $reference, $message, $data);
    }

    public static function successful(string $reference, ?string $message = null, array $data = []): self
    {
        return new self(true, 'successful', $reference, $message, $data);
    }

    public static function failed(string $message, array $data = []): self
    {
        return new self(false, 'failed', null, $message, $data);
    }
}
