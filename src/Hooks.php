<?php

namespace BriefWOO;

use BriefWOO\Exceptions\HookException;
use BriefWOO\Hooks\Hook;
use BriefWOO\Settings\SettingsFields;

class Hooks
{

    public static function register()
    {
        $hooks = [];

        $directory = new \RecursiveDirectoryIterator(BRIEFWOO_DIR . 'src/Hooks');
        $iterator  = new \RecursiveIteratorIterator($directory);
        $fields = new SettingsFields();
        $fields->init();

        /**
         * @var \SplFileInfo $file
         */
        foreach ( $iterator as $file ) {
            if ( $file->getFilename() == 'Filter.php'
                || $file->getFilename() == 'Action.php'
            ) {
                continue;
            }

            if ( ! str_ends_with($file->getFilename(), 'Filter.php')
                && ! str_ends_with($file->getFilename(), 'Action.php')
            ) {
                continue;
            }

            $relativePath = ltrim($file->getPath(), BRIEFWOO_DIR . 'src/');
            $relativePath = str_replace('/', '\\', $relativePath);

            if ( $file->isFile() ) {
                $hooks[] = 'BriefWOO\\' . $relativePath . '\\' . rtrim($file->getFilename(), '.php');
            }
        }

        foreach ( $hooks as $hook ) {
            if ( ! class_exists($hook) ) {
                throw new HookException("Hook class $hook does not exist.");
            }

            if ( ! is_subclass_of($hook, Hook::class) ) {
                throw new HookException('Hook must be a subclass of Hook');
            }

            (new $hook())->register();
        }
    }

}
