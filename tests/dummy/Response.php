<?php

namespace Tests\Dummy;


class Response
{

  public function __construct(private $message) {}


  public function getMessage()
  {
    return $this->message;
  }
}
