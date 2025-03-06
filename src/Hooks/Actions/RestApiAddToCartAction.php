<?php

namespace BriefWOO\Hooks\Actions;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WC_Product_Addons_Helper;
use SemiorbitGuid\Guid;

class RestApiAddToCartAction extends Action
{
  public function __construct()
  {
    $this->name = 'rest_api_init';
  }

  public function handle()
  {
    register_rest_route(BRIEFWOO_DOMAIN . '/' . self::$version, '/add_to_cart', [
      'methods'  => WP_REST_Server::CREATABLE,
      'callback' => [$this, 'add'],
      'permission_callback' => '__return_true',
    ]);

      register_rest_route(BRIEFWOO_DOMAIN . '/' . self::$version, '/add_to_checkout', [
          'methods'  => WP_REST_Server::CREATABLE,
          'callback' => [$this, 'add_checkout'],
          'permission_callback' => '__return_true',
      ]);
  }

  public function add(WP_REST_Request $request)
  {

    $request_addons = $request->get_param('addons');
    if (empty($request_addons) || !is_array($request_addons)) {
      return new WP_REST_Response([
        'message' => 'Invalid addons'
      ], 400);
    }

    $global_addons = WC_Product_Addons_Helper::get_product_addons(BRIEFWOO_PRODUCT_ID);
    if (empty($global_addons)) {
      return new WP_REST_Response([
        'message' => 'Addons not found'
      ], 400);
    }


    $addons = [];

    foreach ($request_addons as $key => $value) {
      $global_addon = array_values(array_filter($global_addons, function ($addon) use ($value) {
        return floatval(trim($addon['id'])) === floatval(trim($value['id']));
      }))[0];

      $option = array_values(array_filter($global_addon['options'], function ($option) use ($value) {
        return trim($option['label']) === trim($value['value']);
      }))[0];

      $addons[] = [
        "id" => $value['id'],
        "name" => $global_addon["name"],
        "value" => $value['value'],
        "price" => floatval($option["price"]) ?? 0,
        "field_name" => $global_addon["field_name"],
        "field_type" => $global_addon["type"],
        "price_type" => $option["price_type"]
      ];
    }

    $added = WC()->cart->add_to_cart(BRIEFWOO_PRODUCT_ID, 1, 0, [], [
      'addons' => $addons,
      'unique_key' => Guid::NewGuid()
    ]);

    if ($added) {
      return new WP_REST_Response([
        'message' => 'Product added to cart'
      ], 200);
    } else {
      return new WP_REST_Response([
        'message' => 'Failed to add product to cart'
      ], 400);
    }
  }

    public function add_checkout(WP_REST_Request $request) {

        error_log('===== Start Debugging Checkout Request =====');
        error_log('Full WP_REST_Request: ' . print_r($request, true));

        // Get JSON data from the request body
        $data = json_decode($request->get_body(), true);
        error_log('Parsed JSON Data: ' . print_r($data, true));

        if (empty($data)) {
            error_log('Error: No data received');
            return new WP_REST_Response(['success' => false, 'message' => 'No data received'], 400);
        }

        $pdf_file = $_FILES['file'] ?? null;


        if (!$pdf_file || $pdf_file['error'] !== UPLOAD_ERR_OK) {
            var_dump($_FILES);
            error_log("Error: No valid PDF file uploaded.");
            return new WP_REST_Response(['success' => false, 'message' => 'No valid PDF file uploaded'], 400);
        }

        $pdf_tmp_path = $pdf_file['tmp_name'];
        error_log("Received PDF file: " . print_r($pdf_file, true));

        // Extract payment method
        $payment_method = sanitize_text_field($data['payment_method'] ?? 'Unknown');
        error_log('Payment Method: ' . $payment_method);

        // Get card data and split card_holder
        $card = $data['card'] ?? [];
        $card_holder = sanitize_text_field($card['card_holder'] ?? 'Unknown Name');
        $name_parts = explode(' ', trim($card_holder), 2);
        $first_name = $name_parts[0] ?? 'Unknown';
        $last_name = $name_parts[1] ?? 'Unknown';


        // Create WooCommerce order
        $order = wc_create_order();
        error_log('Created Order ID: ' . $order->get_id());

        // Set basic order data
        $order->set_billing_first_name($first_name);
        $order->set_billing_last_name($last_name);

        // Set payment method
        $order->set_payment_method($payment_method);
        $order->set_payment_method_title($payment_method);
        $order->set_status('pending');
        // Add products to the order
        if (!empty($data['files'])) {
            $addons_array = [];

            foreach ($data['files'] as $file) {
                $product_id = BRIEFWOO_PRODUCT_ID;
                $quantity = 1;

                $order->add_product(wc_get_product($product_id), $quantity);
                error_log("Added Product ID: {$product_id}, Quantity: {$quantity}");

                // Save file data in meta fields
                $order->update_meta_data('_file_address_text', sanitize_textarea_field($file['address_text']));
                $order->update_meta_data('_file_pages_length', intval($file['pages_length']));
                $order->update_meta_data('_payment_method', sanitize_textarea_field($file['payment_method']));

                // Add Addons as separate meta data
                foreach ($file['addons'] as $addon) {
                    $addons_array[] = [
                        'id' => sanitize_text_field($addon['id']),
                        'value' => sanitize_text_field($addon['value']),
                    ];
                }

                $order->update_meta_data('_addons', wp_json_encode($addons_array));
            }
        }

        // Add payment meta data (for storage only, no security!)
        $order->update_meta_data('_card_number', sanitize_text_field($card['card_number'] ?? 'Not Provided'));
        $order->update_meta_data('_expiry_date', sanitize_text_field($card['expiry_date'] ?? 'Not Provided'));
        $order->update_meta_data('_cvv', sanitize_text_field($card['cvv'] ?? 'Not Provided'));

        // Save order
        $order->save();

        $json_content = json_encode($data, JSON_PRETTY_PRINT);

        $ftp_result = $this->upload_to_ftp($order->get_id(), $json_content, $pdf_tmp_path);

        if ($ftp_result) {
            error_log("FTP Upload successful for Order #{$order->get_id()}");
        } else {
            error_log("FTP Upload failed for Order #{$order->get_id()}");
        }

        // Process payment via hook (simulate successful payment)
        $payment_result = apply_filters('briefwoo_process_payment', [
            'success' => true, // Temporarily simulate successful payment
            'transaction_id' => 'test_txn_' . uniqid(),
            'error' => null
        ], $order, $payment_method);

        error_log('Payment Response: ' . print_r($payment_result, true));

        if ($payment_result['success']) {
            $order->payment_complete();
            $order->set_status('processing');
            $order->save();

            error_log('===== End Debugging Checkout Request =====');

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Order created and paid successfully',
                'order_id' => $order->get_id(),
            ], 200);
        } else {
            $order->set_status('failed');
            $order->save();

            error_log('===== End Debugging Checkout Request =====');

            return new WP_REST_Response([
                'success' => false,
                'message' => 'Payment failed',
                'error' => $payment_result['error'],
            ], 500);
        }
    }

    public function process_payment($order, $payment_method) {
        $order_id = $order->get_id();
        $order_total = $order->get_total();
        $currency = get_woocommerce_currency();

        error_log("Processing payment for Order #{$order_id} using method: {$payment_method}");

        // Use action instead of filter
        do_action('briefwoo_before_process_payment', $order, $payment_method);

        // Simulate payment result
        $payment_result = [
            'success' => true,
            'transaction_id' => 'test_txn_' . uniqid(),
            'error' => null
        ];

        if ($payment_result['success']) {
            error_log("Payment successful for Order #{$order_id}. Transaction ID: " . $payment_result['transaction_id']);
            return [
                'success' => true,
                'transaction_id' => $payment_result['transaction_id']
            ];
        } else {
            error_log("Payment failed for Order #{$order_id}. Error: " . $payment_result['error']);
            return [
                'success' => false,
                'error' => $payment_result['error']
            ];
        }
    }

    /**
     * Upload JSON and PDF files directly to FTP server without saving locally.
     *
     * @param int $order_id The WooCommerce order ID.
     * @param string $json_content JSON data to be uploaded.
     * @param string $pdf_tmp_path Temporary file path of the uploaded PDF.
     * @return bool True on success, false on failure.
     */
    private function upload_to_ftp($order_id, $json_content, $pdf_tmp_path) {
        $ftp_server = "ftp.bit-online.de";
        $ftp_user = "thomas@pixelnerds.de";
        $ftp_pass = "DekHmCkvXg2";

        // Open FTP connection
        $conn_id = ftp_connect($ftp_server);
        if (!$conn_id) {
            error_log("FTP Connection failed to $ftp_server");
            return false;
        }

        // Login to FTP server
        $login_result = ftp_login($conn_id, $ftp_user, $ftp_pass);
        if (!$login_result) {
            error_log("FTP Login failed for user $ftp_user");
            ftp_close($conn_id);
            return false;
        }

        // Enable passive mode
        ftp_pasv($conn_id, true);

        // Define remote file paths
        $json_remote_path = "/order_" . $order_id . ".json";
        $pdf_remote_path = "/order_" . $order_id . ".pdf";

        // Upload JSON file using a temporary stream
        $json_stream = fopen('php://temp', 'r+');
        fwrite($json_stream, $json_content);
        rewind($json_stream);
        if (ftp_fput($conn_id, $json_remote_path, $json_stream, FTP_ASCII)) {
            error_log("JSON file uploaded successfully: " . $json_remote_path);
        } else {
            error_log("Failed to upload JSON file: " . $json_remote_path);
        }
        fclose($json_stream);

        // Upload PDF file from temp path
        if (ftp_put($conn_id, $pdf_remote_path, $pdf_tmp_path, FTP_BINARY)) {
            error_log("PDF file uploaded successfully: " . $pdf_remote_path);
        } else {
            error_log("Failed to upload PDF file: " . $pdf_remote_path);
        }

        // Close FTP connection
        ftp_close($conn_id);
        return true;
    }


}
