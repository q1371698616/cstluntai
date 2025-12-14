&lt;?php
/**
 * 记录查询和数据统计 API
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$database = new Database();
$db = $database->getConnection();

// 查询入库记录
if ($method === 'GET' && isset($_GET['type']) && $_GET['type'] === 'inbound') {
    $userData = Auth::authenticate();

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $pageSize = isset($_GET['page_size']) ? (int)$_GET['page_size'] : DEFAULT_PAGE_SIZE;
    $offset = ($page - 1) * $pageSize;

    $barcode = isset($_GET['barcode']) ? trim($_GET['barcode']) : null;
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    $operatorId = isset($_GET['operator_id']) ? (int)$_GET['operator_id'] : null;

    try {
        $where = ['1=1'];
        $params = [];

        if ($barcode) {
            $where[] = 'i.barcode = :barcode';
            $params[':barcode'] = $barcode;
        }
        if ($startDate) {
            $where[] = 'i.inbound_time >= :start_date';
            $params[':start_date'] = $startDate . ' 00:00:00';
        }
        if ($endDate) {
            $where[] = 'i.inbound_time <= :end_date';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        if ($operatorId) {
            $where[] = 'i.operator_id = :operator_id';
            $params[':operator_id'] = $operatorId;
        }

        $whereClause = implode(' AND ', $where);

        // 查询总数
        $countSql = "SELECT COUNT(*) as total FROM inbound_records i WHERE {$whereClause}";
        $countStmt = $db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // 查询记录列表
        $sql = "SELECT i.*, p.name as product_name, p.model as product_model
                FROM inbound_records i
                LEFT JOIN products p ON i.product_id = p.id
                WHERE {$whereClause}
                ORDER BY i.inbound_time DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success([
            'list' => $records,
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => ceil($total / $pageSize)
            ]
        ]);
    } catch (Exception $e) {
        Response::serverError('查询入库记录失败');
    }
}

// 查询出库记录
if ($method === 'GET' && isset($_GET['type']) && $_GET['type'] === 'outbound') {
    $userData = Auth::authenticate();

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $pageSize = isset($_GET['page_size']) ? (int)$_GET['page_size'] : DEFAULT_PAGE_SIZE;
    $offset = ($page - 1) * $pageSize;

    $barcode = isset($_GET['barcode']) ? trim($_GET['barcode']) : null;
    $licensePlate = isset($_GET['license_plate']) ? trim($_GET['license_plate']) : null;
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    $operatorId = isset($_GET['operator_id']) ? (int)$_GET['operator_id'] : null;

    try {
        $where = ['1=1'];
        $params = [];

        if ($barcode) {
            $where[] = 'o.barcode = :barcode';
            $params[':barcode'] = $barcode;
        }
        if ($licensePlate) {
            $where[] = 'o.license_plate LIKE :license_plate';
            $params[':license_plate'] = "%{$licensePlate}%";
        }
        if ($startDate) {
            $where[] = 'o.outbound_time >= :start_date';
            $params[':start_date'] = $startDate . ' 00:00:00';
        }
        if ($endDate) {
            $where[] = 'o.outbound_time <= :end_date';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        if ($operatorId) {
            $where[] = 'o.operator_id = :operator_id';
            $params[':operator_id'] = $operatorId;
        }

        $whereClause = implode(' AND ', $where);

        // 查询总数
        $countSql = "SELECT COUNT(*) as total FROM outbound_records o WHERE {$whereClause}";
        $countStmt = $db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // 查询记录列表
        $sql = "SELECT o.*, p.name as product_name, p.model as product_model
                FROM outbound_records o
                LEFT JOIN products p ON o.product_id = p.id
                WHERE {$whereClause}
                ORDER BY o.outbound_time DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success([
            'list' => $records,
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => ceil($total / $pageSize)
            ]
        ]);
    } catch (Exception $e) {
        Response::serverError('查询出库记录失败');
    }
}

// 数据大屏统计
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'dashboard') {
    $userData = Auth::authenticate();

    try {
        // 商品总数
        $productCountSql = "SELECT COUNT(*) as count FROM products WHERE status = 1";
        $productCount = $db->query($productCountSql)->fetch(PDO::FETCH_ASSOC)['count'];

        // 条形码总数
        $barcodeCountSql = "SELECT COUNT(*) as count FROM barcodes";
        $barcodeCount = $db->query($barcodeCountSql)->fetch(PDO::FETCH_ASSOC)['count'];

        // 库存总量
        $totalStockSql = "SELECT COALESCE(SUM(stock), 0) as total FROM barcodes";
        $totalStock = $db->query($totalStockSql)->fetch(PDO::FETCH_ASSOC)['total'];

        // 今日入库量
        $todayInboundSql = "SELECT COALESCE(SUM(quantity), 0) as total
                            FROM inbound_records
                            WHERE DATE(inbound_time) = CURDATE()";
        $todayInbound = $db->query($todayInboundSql)->fetch(PDO::FETCH_ASSOC)['total'];

        // 累计入库量
        $totalInboundSql = "SELECT COALESCE(SUM(quantity), 0) as total FROM inbound_records";
        $totalInbound = $db->query($totalInboundSql)->fetch(PDO::FETCH_ASSOC)['total'];

        // 今日出库量
        $todayOutboundSql = "SELECT COALESCE(SUM(quantity), 0) as total
                             FROM outbound_records
                             WHERE DATE(outbound_time) = CURDATE()";
        $todayOutbound = $db->query($todayOutboundSql)->fetch(PDO::FETCH_ASSOC)['total'];

        // 累计出库量
        $totalOutboundSql = "SELECT COALESCE(SUM(quantity), 0) as total FROM outbound_records";
        $totalOutbound = $db->query($totalOutboundSql)->fetch(PDO::FETCH_ASSOC)['total'];

        // 低库存预警（库存小于10的条形码）
        $lowStockSql = "SELECT COUNT(*) as count FROM barcodes WHERE stock > 0 AND stock < 10";
        $lowStockCount = $db->query($lowStockSql)->fetch(PDO::FETCH_ASSOC)['count'];

        // 近7天入库趋势
        $weekInboundSql = "SELECT DATE(inbound_time) as date, SUM(quantity) as total
                           FROM inbound_records
                           WHERE inbound_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                           GROUP BY DATE(inbound_time)
                           ORDER BY date ASC";
        $weekInbound = $db->query($weekInboundSql)->fetchAll(PDO::FETCH_ASSOC);

        // 近7天出库趋势
        $weekOutboundSql = "SELECT DATE(outbound_time) as date, SUM(quantity) as total
                            FROM outbound_records
                            WHERE outbound_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                            GROUP BY DATE(outbound_time)
                            ORDER BY date ASC";
        $weekOutbound = $db->query($weekOutboundSql)->fetchAll(PDO::FETCH_ASSOC);

        // 热门商品TOP10（按出库次数）
        $topProductsSql = "SELECT p.id, p.name, p.model, COUNT(*) as outbound_count, SUM(o.quantity) as total_quantity
                           FROM outbound_records o
                           LEFT JOIN products p ON o.product_id = p.id
                           GROUP BY p.id
                           ORDER BY outbound_count DESC
                           LIMIT 10";
        $topProducts = $db->query($topProductsSql)->fetchAll(PDO::FETCH_ASSOC);

        Response::success([
            'summary' => [
                'product_count' => (int)$productCount,
                'barcode_count' => (int)$barcodeCount,
                'total_stock' => (int)$totalStock,
                'today_inbound' => (int)$todayInbound,
                'total_inbound' => (int)$totalInbound,
                'today_outbound' => (int)$todayOutbound,
                'total_outbound' => (int)$totalOutbound,
                'low_stock_count' => (int)$lowStockCount
            ],
            'trends' => [
                'week_inbound' => $weekInbound,
                'week_outbound' => $weekOutbound
            ],
            'top_products' => $topProducts
        ]);
    } catch (Exception $e) {
        Response::serverError('获取统计数据失败');
    }
}

// 用户管理（管理员）
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'users') {
    $userData = Auth::authenticate(true);

    try {
        $sql = "SELECT id, username, realname, phone, email, role, status, created_at,
                (SELECT COUNT(*) FROM inbound_records WHERE operator_id = users.id) as inbound_count,
                (SELECT COUNT(*) FROM outbound_records WHERE operator_id = users.id) as outbound_count
                FROM users
                ORDER BY created_at DESC";

        $stmt = $db->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success($users);
    } catch (Exception $e) {
        Response::serverError('获取用户列表失败');
    }
}

// 更新用户状态（管理员）
if ($method === 'PUT' && isset($_GET['action']) && $_GET['action'] === 'user-status') {
    $userData = Auth::authenticate(true);
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['user_id']) || !isset($data['status'])) {
        Response::validationError('用户ID和状态不能为空');
    }

    $userId = (int)$data['user_id'];
    $status = (int)$data['status'];

    // 不允许禁用自己
    if ($userId === $userData['user_id']) {
        Response::error('不能修改自己的状态');
    }

    try {
        $sql = "UPDATE users SET status = :status WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $userId);

        if ($stmt->execute()) {
            Response::success(null, '用户状态更新成功');
        } else {
            Response::serverError('用户状态更新失败');
        }
    } catch (Exception $e) {
        Response::serverError('用户状态更新失败');
    }
}

Response::notFound('接口不存在');
?&gt;
