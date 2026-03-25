<?php

declare(strict_types=1);

namespace Result;

use Throwable;

/**
 * @template T
 */
interface Item
{
    /**
     * @return T
     */
    public function grab(): mixed;
}
