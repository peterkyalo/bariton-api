<?php

namespace App\Services\Payments;

class RefundResult
{
    public function __construct(
        public bool $success,
        public ?string $reference = null,
        public ?string $message = null,
        public array $data = []
    ) {}

    public static function successful(string $reference, ?string $message = null, array $data = []): self
    {
        return new self(true, $reference, $message, $data);
    }

    public static function failed(string $message, array $data = []): self
    {
        return new self(false, null, $message, $data);
    }

    public function __get(string $name)
    {
        if ($name === 'refundReference') {
            return $this->reference;
        }
        return null;
    }
}
