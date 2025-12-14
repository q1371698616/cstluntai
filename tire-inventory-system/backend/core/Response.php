&lt;?php
/**
 * API 响应类
 */
class Response {
    /**
     * 成功响应
     */
    public static function success($data = null, $message = '操作成功', $code = 200) {
        http_response_code($code);
        echo json_encode([
            'code' => $code,
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 失败响应
     */
    public static function error($message = '操作失败', $code = 400, $data = null) {
        http_response_code($code);
        echo json_encode([
            'code' => $code,
            'success' => false,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 未授权响应
     */
    public static function unauthorized($message = '未授权访问') {
        self::error($message, 401);
    }

    /**
     * 禁止访问响应
     */
    public static function forbidden($message = '禁止访问') {
        self::error($message, 403);
    }

    /**
     * 未找到响应
     */
    public static function notFound($message = '资源不存在') {
        self::error($message, 404);
    }

    /**
     * 验证失败响应
     */
    public static function validationError($message = '数据验证失败', $errors = null) {
        self::error($message, 422, $errors);
    }

    /**
     * 服务器错误响应
     */
    public static function serverError($message = '服务器内部错误') {
        self::error($message, 500);
    }
}
?&gt;
