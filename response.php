<?php

class Response {

    public static function success_return($result = [], $message = 'Success', $code = 200) {
        $response = array(
            'status' => true,
            'response' => $result,
            'message' => $message
        );
        http_response_code($code);
        echo json_encode($response);
    }

    public static function success($message = 'Success', $code = 200) {
        $response = array(
            'status' => true,
            'message' => $message
        );
        http_response_code($code);
        echo json_encode($response);
    }

    public static function error($message = 'Error', $code = 400) {
        $response = array(
            'status' => false,
            'message' => $message
        );
        http_response_code($code);
        echo json_encode($response);
    }

}

?>