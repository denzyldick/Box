<?php

declare(strict_types=1);

use Result\Item;

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
