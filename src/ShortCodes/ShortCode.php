<?php

namespace BriefWOO\ShortCodes;

use BriefWOO\Exceptions\ShortCodeException;

class ShortCode
{
  protected string $name;
  protected array $scripts = [];
  protected array $styles = [];
  public static $showCount = [];
  protected int $index = 0;

  public function register()
  {
    add_shortcode($this->name, [$this, 'prepare']);
  }

  public function prepare(array $attributes = [])
  {
    foreach ($this->scripts as $script) {
      wp_enqueue_script($script, $script, [], false, true);
    }

    foreach ($this->styles as $style) {
      wp_enqueue_style($style, $style, [], false, true);
    }

    $this->incrementShowCount();

    return $this->show($attributes);
  }

  public function incrementShowCount()
  {
    if (!isset(static::$showCount[$this->name])) {
      static::$showCount[$this->name] = 0;
    }

    static::$showCount[$this->name] += 1;
    $this->index = static::$showCount[$this->name];
  }

  public function show(array $attributes = [])
  {
    throw new ShortCodeException('Shortcode must implement show method');
  }
}
