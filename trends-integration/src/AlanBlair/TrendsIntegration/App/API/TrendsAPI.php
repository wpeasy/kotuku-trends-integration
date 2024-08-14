<?php
/*
 * @see https://www.trends.nz/api
 */

namespace AlanBlair\TrendsIntegration\App\API;

use AlanBlair\TrendsIntegration\App\ResponseStatusMap;

class TrendsAPI
{
    const supplier_name = 'trends';

    private static $_instance;

    public static function get_instance()
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct()
    {

    }


    public function request($url, $type = 'GET', $username = '', $password = '', $postData = array())
    {
        // create curl resource
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // set url
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
        if ($type == 'POST' && is_array($postData) && !empty($postData)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }

        $headers = array();
        $headers[] = "Accept: application/json";
        if ($username !== '' && $password !== '') {
            $headers[] = "Authorization: Basic " . base64_encode($username . ":" . $password);
        } else {
            return new \WP_Error(ResponseStatusMap::STATUS_CRITICAL, 'Authorization could not be established, please supply connection details.');
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return new \WP_Error(ResponseStatusMap::STATUS_CRITICAL, 'Error:' . curl_error($ch));
        }
        curl_close($ch);

        $response = (array)json_decode($result);

        if (isset($response['status']) && $response['status'] === 200) {

            return $response;
        } else {
            return new \WP_Error(ResponseStatusMap::STATUS_CRITICAL, 'Connection Failed: ' . print_r($result, true));
        }
    }
}