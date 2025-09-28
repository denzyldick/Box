<?php

declare(strict_types=1);

namespace Result;

use Error;
use Exception;
use PDO;
use Throwable;
use TypeError;

/**
 *
 */
enum Result
{
  case Ok;

  case Error;

  /**
   * This currenlty holds the object that should be called later. 
   * At this moment only object works. I need to think if holding an array is usefull.
   */
  public function hold(array|object $object): self
  {

    $hash = 'test';
    Hold::$map[$hash] = $object;
    return $this;
  }

  /**
   * Collect the real object from the bag.
   * @return Exception
   */
  public function exception(): Throwable
  {
    return Hold::pop();
  }

  /**
   */
  public function collect(): object
  {
    return Hold::pop();
  }


  public function map(callable $callback): self
  {
    Hold::$map['test'] = array_map($callback, Hold::pop());
    return $this;
  }
  public function filter(callable $callback): self
  {
    Hold::$map['test'] = array_filter(Hold::pop(), $callback);
    return $this;
  }

  public function isErr(): bool
  {
    return true;
  }

  public function isOk(): bool
  {
    return true;
  }

  public function unWrap(): array|object
  {
    return Hold::pop();
  }

  public function ok() {}

  public function err() {}
}
