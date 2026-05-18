<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Result\Item;

/**
 * @implements Item<array>
 */
class UserList implements Item
{
    public function grab(): array|object
    {
        return [
            [
                'name' => 'Jhon doe',
                'active' => true,
            ],
            [
                'name' => 'Sandra doe',
                'active' => false,
            ],
        ];
    }
}
