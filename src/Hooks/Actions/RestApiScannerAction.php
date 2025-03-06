<?php

namespace BriefWOO\Hooks\Actions;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class RestApiScannerAction extends Action
{
  public function __construct()
  {
    $this->name = 'rest_api_init';
  }

  public function handle()
  {
    register_rest_route(BRIEFWOO_DOMAIN . '/' . self::$version, '/scanner', [
      'methods'  => WP_REST_Server::CREATABLE,
      'callback' => [$this, 'scanner'],
      'permission_callback' => '__return_true',
    ]);
  }

  /**
   * Handle incoming file and forward it to scanner server.
   *
   * @param WP_REST_Request $request The REST request.
   * @return WP_REST_Response The response.
   */
  public function scanner(WP_REST_Request $request)
  {
    if (empty($_FILES['file'])) {
      return new WP_REST_Response(array(
        'success' => false,
        'message' => 'No file uploaded.',
      ), 400);
    }

    $file = $_FILES['file'];
    $file_path = $file['tmp_name'];
    $file_name = $file['name'];
    $file_type = $file['type'];

    // Check if the file exists and is readable.
    if (!is_readable($file_path)) {
      return new WP_REST_Response(array(
        'success' => false,
        'message' => 'Uploaded file cannot be read.',
      ), 500);
    }

    // Prepare the remote URL and headers.
    $remote_url = BRIEFWOO_API_BASE . '/scanner/';

    $boundary = wp_generate_password(24);
    $headers  = array(
      'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
      'Authorization' => BRIEFWOO_API_TOKEN,
    );

    $payload = '';
    if ($file_path) {
      $payload .= '--' . $boundary;
      $payload .= "\r\n";
      $payload .= 'Content-Disposition: form-data; name="' . 'file' .
        '"; filename="' . $file_name . '"' . "\r\n";
      $payload .= "\r\n";
      $payload .= file_get_contents($file_path);
      $payload .= "\r\n";
    }

    $payload .= '--' . $boundary . '--';

    $response = wp_remote_post($remote_url, array(
      'body'    => $payload,
      'headers' => $headers,
      'timeout' => 45,
    ));

      // Handle errors in the response.
    if (is_wp_error($response)) {
      return new WP_REST_Response(array(
        'success' => false,
        'message' => $response->get_error_message(),
      ), 500);
    }

    // Retrieve the response details.
    $response_body = wp_remote_retrieve_body($response);
    $response_code = wp_remote_retrieve_response_code($response);
    $response_headers = wp_remote_retrieve_headers($response);
    $content_type = isset($response_headers['content-type']) ? $response_headers['content-type'] : '';

    if (isset($response_headers['Scanner-Status'])) {
      header('Scanner-Status: ' . $response_headers['Scanner-Status']);
    }

    if (isset($response_headers['Scanner-Address'])) {
      header('Scanner-Address: ' . $response_headers['Scanner-Address']);
    }

    if (isset($response_headers['Content-Disposition'])) {
      header('Content-Disposition: ' . $response_headers['Content-Disposition']);
    }

    if (isset($response_headers['File-ID'])) {
      header('File-ID: ' . $response_headers['File-ID']);
    }

    if (isset($response_headers['File-Pages-Length'])) {
      header('File-Pages-Length: ' . $response_headers['File-Pages-Length']);
    }

    if ($response_code === 200) {
      // Check if the response is an image (or binary data).
      if (strpos($content_type, 'image/') !== false) {
        header('Content-Type: ' . $content_type);
        echo $response_body;
        exit;
      } else {
        $rest_response = new WP_REST_Response(json_decode($response_body, true), $response_code);
        return $rest_response;
      }
    } else {
      return new WP_REST_Response(json_decode($response_body, true), $response_code);
    }
  }
}
