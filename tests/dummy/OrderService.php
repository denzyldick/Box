<?php

declare(strict_types=1);

namespace Tests\Dummy;

class OrderService
{
    /**
     *
     * @param ArrayAccess $array
     * @return string
     */
    public function handle(Response $array): string
    {
        return $array->getMessage();
    }
}
