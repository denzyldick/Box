<?php

declare(strict_types=1);

namespace Result;

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
