<?php

namespace App\Channels\Messages;

class SmsMessage
{
    public function __construct(
        public string $content = '',
        public ?string $to = null,
        public ?string $from = null
    ) {}

    public function line(string $line): self
    {
        $this->content .= ($this->content ? "\n" : '').$line;

        return $this;
    }

    public function to(string $to): self
    {
        $this->to = $to;

        return $this;
    }

    public function from(string $from): self
    {
        $this->from = $from;

        return $this;
    }
}
