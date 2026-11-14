<?php

namespace App\Data;

readonly class PurchaseLimit
{
    public function __construct(
        public int $max,
        public int $used,
    ) {}
}
