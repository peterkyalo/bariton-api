<?php

namespace App\Services\Payments;

class RefundResult
{
    public function __construct(
        public bool $success,
        public ?string $refundReference = null,
        public ?string $message = null,
        public array $data = []
    ) {}

    public static function successful(string $refundReference, ?string $message = null, array $data = []): self
    {
        return new self(true, $refundReference, $message, $data);
    }

    public static function failed(string $message, array $data = []): self
    {
        return new self(false, null, $message, $data);
    }
}
