<?php

namespace BriefWOO\ShortCodes;

use BriefWOO\ShortCodes\ShortCode;
use WC_Product_Addons_Helper;


class BriefShortCode extends ShortCode
{
  protected string $name = 'briefwoo_shortcode';
  protected array $scripts = [];

  public function show(array $attributes = [])
  {
    $jsDependencies = [];
    $localizeScriptHandler = '';

    if (BRIEFWOO_ENV === 'development') {
      $localizeScriptHandler = 'module:' . BRIEFWOO_DOMAIN . '-vite-client';

      wp_register_script($localizeScriptHandler, '//' . BRIEFWOO_DEVELOPMENT_DOMAIN . '/@vite/client', [], null, false);
      wp_enqueue_script($localizeScriptHandler);

      wp_register_script('module:' . BRIEFWOO_DOMAIN . '-vite-main', '//' . BRIEFWOO_DEVELOPMENT_DOMAIN . '/src/main.tsx', $jsDependencies, null, true);
      wp_enqueue_script('module:' . BRIEFWOO_DOMAIN . '-vite-main');

      add_filter('script_loader_tag', array($this, 'addModuleToScript'), 10, 3);
    } else {
      $localizeScriptHandler = BRIEFWOO_DOMAIN . '-app';

      wp_register_script($localizeScriptHandler, BRIEFWOO_URL . 'assets/dist/app/main.js', $jsDependencies, BRIEFWOO_VERSION, true);
      wp_enqueue_script($localizeScriptHandler);

      wp_register_style($localizeScriptHandler, BRIEFWOO_URL . 'assets/dist/app/style.css', null, BRIEFWOO_VERSION, 'all');
      wp_enqueue_style($localizeScriptHandler);
    }

    $blogId = get_current_blog_id();

    $global_addons = WC_Product_Addons_Helper::get_product_addons(BRIEFWOO_PRODUCT_ID);
    $product = wc_get_product(BRIEFWOO_PRODUCT_ID);

      $tooltips = [
          'standardversand' => carbon_get_theme_option('standardversand'),
          'einschreiben' => carbon_get_theme_option('einschreiben'),
          'simplex' => carbon_get_theme_option('simplex'),
          'duplex' => carbon_get_theme_option('duplex'),
          'standardpapier' => carbon_get_theme_option('standardpapier'),
          'umweltpapier' => carbon_get_theme_option('umweltpapier'),
      ];

      $delivery_date = $this->get_delivery_date();

    wp_localize_script(
      $localizeScriptHandler,
      'BriefWooData',
      [
        'api_base' => get_rest_url($blogId, '/' . BRIEFWOO_DOMAIN . '/v1'),
        'nonce' => wp_create_nonce('wp_rest'),
        'wc_cart_url' => wc_get_cart_url(),
        'example_pdf_jpg' => BRIEFWOO_URL . 'assets/example-preview.jpg',
        'prices' => [
          'product' => [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price' => (float) $product->get_sale_price(),
          ],
          'addons' => $global_addons,
          'tooltips' => $tooltips,
          'delivery_date' => $delivery_date,
        ]
      ],
    );

    $content = "";

//    $content = wp_get_inline_script_tag('import { injectIntoGlobalHook } from "//' . BRIEFWOO_DEVELOPMENT_DOMAIN . '/@react-refresh";injectIntoGlobalHook(window);window.$RefreshReg$ = () => {};window.$RefreshSig$ = () => (type) => type;', ['type' => 'module']);

    $content .= '<div class="' . BRIEFWOO_DOMAIN . '-app alignfull"></div>';
      error_log(print_r($content, true));

    return $content;
  }


  /**
   * Add module type to script tag.
   *
   * @param string $tag The script tag.
   * @param string $handle The script handle.
   * @param string $src The script source.
   */
  public function addModuleToScript($tag, $handle, $src)
  {
    if (str_starts_with($handle, 'module:' . BRIEFWOO_DOMAIN)) {
      $tag = '<script type="module" src="' . esc_url($src) . '"></script>';
    }

    return $tag;
  }

    private function get_delivery_date($start_date = 'today', $days_to_add = 3)
    {
        $date = new \DateTime($start_date);
        $added_days = 0;

        while ($added_days < $days_to_add) {
            $date->modify('+1 day');

            if ($date->format('N') < 6) {
                $added_days++;
            }
        }

        setlocale(LC_TIME, 'de_DE.UTF-8', 'de_DE', 'de', 'ge');

        return strftime('%e. %B %Y', $date->getTimestamp());

    }
}
