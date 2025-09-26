<?php

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
        'active' => false
      ]
    ];
  }
}
