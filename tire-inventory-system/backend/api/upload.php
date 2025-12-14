&lt;?php
/**
 * 文件上传 API
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];

// 上传文件
if ($method === 'POST') {
    $userData = Auth::authenticate();

    if (!isset($_FILES['file'])) {
        Response::validationError('请选择要上传的文件');
    }

    $file = $_FILES['file'];
    $uploadType = isset($_GET['type']) ? $_GET['type'] : 'products'; // products 或 license_plates

    // 验证文件
    if ($file['error'] !== UPLOAD_ERR_OK) {
        Response::error('文件上传失败');
    }

    // 检查文件大小
    if ($file['size'] > MAX_FILE_SIZE) {
        Response::error('文件大小不能超过5MB');
    }

    // 检查文件类型
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        Response::error('只支持上传 JPG、PNG、GIF 格式的图片');
    }

    try {
        // 生成文件名
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = date('YmdHis') . '_' . uniqid() . '.' . $extension;

        // 确定上传目录
        $uploadDir = $uploadType === 'license_plates' ? 'license_plates' : 'products';
        $targetDir = UPLOAD_DIR . $uploadDir . '/';

        // 创建目录（如果不存在）
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . $fileName;

        // 移动文件
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // 返回相对路径
            $relativePath = 'uploads/' . $uploadDir . '/' . $fileName;

            Response::success([
                'file_name' => $fileName,
                'file_path' => $relativePath,
                'file_url' => '/' . $relativePath,
                'file_size' => $file['size']
            ], '文件上传成功');
        } else {
            Response::serverError('文件保存失败');
        }
    } catch (Exception $e) {
        Response::serverError('文件上传失败');
    }
}

// Base64 上传（用于小程序/APP）
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'base64') {
    $userData = Auth::authenticate();
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['base64'])) {
        Response::validationError('base64数据不能为空');
    }

    $uploadType = isset($data['type']) ? $data['type'] : 'products';

    try {
        // 解析 base64
        $base64String = $data['base64'];

        // 提取文件类型
        if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
            $base64String = substr($base64String, strpos($base64String, ',') + 1);
            $extension = strtolower($type[1]);

            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                Response::error('不支持的图片格式');
            }
        } else {
            $extension = 'jpg';
        }

        $base64String = str_replace(' ', '+', $base64String);
        $imageData = base64_decode($base64String);

        if ($imageData === false) {
            Response::error('base64解码失败');
        }

        // 检查文件大小
        if (strlen($imageData) > MAX_FILE_SIZE) {
            Response::error('文件大小不能超过5MB');
        }

        // 生成文件名
        $fileName = date('YmdHis') . '_' . uniqid() . '.' . $extension;

        // 确定上传目录
        $uploadDir = $uploadType === 'license_plates' ? 'license_plates' : 'products';
        $targetDir = UPLOAD_DIR . $uploadDir . '/';

        // 创建目录
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . $fileName;

        // 保存文件
        if (file_put_contents($targetPath, $imageData)) {
            $relativePath = 'uploads/' . $uploadDir . '/' . $fileName;

            Response::success([
                'file_name' => $fileName,
                'file_path' => $relativePath,
                'file_url' => '/' . $relativePath,
                'file_size' => strlen($imageData)
            ], '文件上传成功');
        } else {
            Response::serverError('文件保存失败');
        }
    } catch (Exception $e) {
        Response::serverError('文件上传失败');
    }
}

Response::notFound('接口不存在');
?&gt;
