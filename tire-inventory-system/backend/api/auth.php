&lt;?php
/**
 * 用户认证 API
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$database = new Database();
$db = $database->getConnection();

// 注册
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'register') {
    $data = json_decode(file_get_contents("php://input"), true);

    // 数据验证
    if (empty($data['username']) || empty($data['password'])) {
        Response::validationError('用户名和密码不能为空');
    }

    if (strlen($data['username']) < 3 || strlen($data['username']) > 50) {
        Response::validationError('用户名长度必须在3-50个字符之间');
    }

    if (strlen($data['password']) < 6) {
        Response::validationError('密码长度不能少于6位');
    }

    // 检查用户名是否已存在
    $checkSql = "SELECT id FROM users WHERE username = :username";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->bindParam(':username', $data['username']);
    $checkStmt->execute();

    if ($checkStmt->rowCount() > 0) {
        Response::error('用户名已存在');
    }

    // 创建用户
    $sql = "INSERT INTO users (username, password, realname, phone, email, role)
            VALUES (:username, :password, :realname, :phone, :email, 'user')";

    $stmt = $db->prepare($sql);
    $hashedPassword = Auth::hashPassword($data['password']);

    $stmt->bindParam(':username', $data['username']);
    $stmt->bindParam(':password', $hashedPassword);
    $stmt->bindParam(':realname', $data['realname']);
    $stmt->bindParam(':phone', $data['phone']);
    $stmt->bindParam(':email', $data['email']);

    if ($stmt->execute()) {
        Response::success(['user_id' => $db->lastInsertId()], '注册成功');
    } else {
        Response::serverError('注册失败');
    }
}

// 登录
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'login') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['username']) || empty($data['password'])) {
        Response::validationError('用户名和密码不能为空');
    }

    $sql = "SELECT id, username, password, realname, role, status FROM users WHERE username = :username";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':username', $data['username']);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        Response::error('用户名或密码错误', 401);
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 检查用户状态
    if ($user['status'] == 0) {
        Response::forbidden('账号已被禁用');
    }

    // 验证密码
    if (!Auth::verifyPassword($data['password'], $user['password'])) {
        Response::error('用户名或密码错误', 401);
    }

    // 生成 Token
    $token = Auth::generateToken($user['id'], $user['username'], $user['role']);

    Response::success([
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'realname' => $user['realname'],
            'role' => $user['role']
        ]
    ], '登录成功');
}

// 获取当前用户信息
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'me') {
    $userData = Auth::authenticate();

    $sql = "SELECT id, username, realname, phone, email, role, created_at FROM users WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $userData['user_id']);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        Response::success($user);
    } else {
        Response::notFound('用户不存在');
    }
}

// 修改密码
if ($method === 'PUT' && isset($_GET['action']) && $_GET['action'] === 'change-password') {
    $userData = Auth::authenticate();
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['old_password']) || empty($data['new_password'])) {
        Response::validationError('旧密码和新密码不能为空');
    }

    if (strlen($data['new_password']) < 6) {
        Response::validationError('新密码长度不能少于6位');
    }

    // 获取当前密码
    $sql = "SELECT password FROM users WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $userData['user_id']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 验证旧密码
    if (!Auth::verifyPassword($data['old_password'], $user['password'])) {
        Response::error('旧密码错误');
    }

    // 更新密码
    $updateSql = "UPDATE users SET password = :password WHERE id = :id";
    $updateStmt = $db->prepare($updateSql);
    $hashedPassword = Auth::hashPassword($data['new_password']);
    $updateStmt->bindParam(':password', $hashedPassword);
    $updateStmt->bindParam(':id', $userData['user_id']);

    if ($updateStmt->execute()) {
        Response::success(null, '密码修改成功');
    } else {
        Response::serverError('密码修改失败');
    }
}

// 获取用户统计信息
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'stats') {
    $userData = Auth::authenticate();

    // 入库统计
    $inboundSql = "SELECT COUNT(*) as total_inbound FROM inbound_records WHERE operator_id = :user_id";
    $inboundStmt = $db->prepare($inboundSql);
    $inboundStmt->bindParam(':user_id', $userData['user_id']);
    $inboundStmt->execute();
    $inboundStats = $inboundStmt->fetch(PDO::FETCH_ASSOC);

    // 出库统计
    $outboundSql = "SELECT COUNT(*) as total_outbound FROM outbound_records WHERE operator_id = :user_id";
    $outboundStmt = $db->prepare($outboundSql);
    $outboundStmt->bindParam(':user_id', $userData['user_id']);
    $outboundStmt->execute();
    $outboundStats = $outboundStmt->fetch(PDO::FETCH_ASSOC);

    Response::success([
        'total_inbound' => (int)$inboundStats['total_inbound'],
        'total_outbound' => (int)$outboundStats['total_outbound'],
        'total_operations' => (int)$inboundStats['total_inbound'] + (int)$outboundStats['total_outbound']
    ]);
}

Response::notFound('接口不存在');
?&gt;
