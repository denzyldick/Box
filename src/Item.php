<?php

declare(strict_types=1);

namespace Result;

interface Item
{
    public function grab(): array|object;
}
