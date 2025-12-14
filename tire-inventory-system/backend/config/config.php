&lt;?php
/**
 * 系统配置文件
 */

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 错误报告（生产环境请关闭）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS 跨域设置
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// JWT 密钥
define('JWT_SECRET_KEY', 'your_secret_key_here_change_in_production');
define('JWT_ALGORITHM', 'HS256');

// 文件上传配置
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// 分页配置
define('DEFAULT_PAGE_SIZE', 20);

// 系统配置
define('SYSTEM_NAME', '轮胎库存管理系统');
define('SYSTEM_VERSION', '1.0.0');
?&gt;
