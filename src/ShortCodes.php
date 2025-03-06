<?php

namespace BriefWOO;

use BriefWOO\Exceptions\ShortCodeException;
use BriefWOO\ShortCodes\ShortCode;

class ShortCodes
{
  public static function register()
  {
    $shortCodes = [];

    $directory = new \RecursiveDirectoryIterator(BRIEFWOO_DIR . 'src/ShortCodes');
    $iterator = new \RecursiveIteratorIterator($directory);

    /**
     * @var \SplFileInfo $file
     */
    foreach ($iterator as $file) {


      if (!str_ends_with($file->getFilename(), 'ShortCode.php') || $file->getFilename() === 'ShortCode.php') {
        continue;
      }

      if ($file->isFile()) {
        $shortCodes[] = 'BriefWOO\\ShortCodes\\' . rtrim($file->getFilename(), '.php');
      }
    }


    foreach ($shortCodes as $shortCode) {
      if (!class_exists($shortCode)) {
        throw new ShortCodeException("$shortCode shortcode class does not exist");
      }

      if (!is_subclass_of($shortCode, ShortCode::class)) {
        throw new ShortCodeException("$shortCode shortcode must be a subclass of ShortCode");
      }

      (new $shortCode())->register();
    }
  }
}
