<?php

namespace BriefWOO\Hooks\Actions;

class WCIsRestAction extends Action
{
  public function __construct()
  {
    $this->name = 'woocommerce_init';
  }

  public function handle()
  {
    if (! WC()->is_rest_api_request()) {
      return;
    }

    WC()->frontend_includes();

    if (null === WC()->cart && function_exists('wc_load_cart')) {
      wc_load_cart();
    }
  }
}
