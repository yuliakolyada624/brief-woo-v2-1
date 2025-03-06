<?php

namespace BriefWOO\Hooks\Actions;

use BriefWOO\Hooks\Hook;
use Carbon_Fields\Carbon_Fields;

class Action extends Hook
{
  protected string $name;
  protected int $priority = 10;
  public static $version = 'v1';
  protected int $acceptedArgs = 0;


  public function register()
  {
    add_action($this->name, [$this, 'handle'], $this->priority, $this->acceptedArgs);
  }
}
