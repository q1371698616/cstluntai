&lt;?php
/**
 * 条形码管理 API
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$database = new Database();
$db = $database->getConnection();

// 获取条形码列表
if ($method === 'GET' && !isset($_GET['barcode']) && !isset($_GET['id'])) {
    $userData = Auth::authenticate();

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $pageSize = isset($_GET['page_size']) ? (int)$_GET['page_size'] : DEFAULT_PAGE_SIZE;
    $offset = ($page - 1) * $pageSize;

    $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
    $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : null;

    try {
        $where = ['1=1'];
        $params = [];

        if ($productId) {
            $where[] = 'b.product_id = :product_id';
            $params[':product_id'] = $productId;
        }
        if ($keyword) {
            $where[] = '(b.barcode LIKE :keyword OR p.name LIKE :keyword OR b.location LIKE :keyword)';
            $params[':keyword'] = "%{$keyword}%";
        }

        $whereClause = implode(' AND ', $where);

        // 查询总数
        $countSql = "SELECT COUNT(*) as total FROM barcodes b
                     LEFT JOIN products p ON b.product_id = p.id
                     WHERE {$whereClause}";
        $countStmt = $db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // 查询列表
        $sql = "SELECT b.*, p.name as product_name, p.model as product_model
                FROM barcodes b
                LEFT JOIN products p ON b.product_id = p.id
                WHERE {$whereClause}
                ORDER BY b.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $barcodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success([
            'list' => $barcodes,
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => ceil($total / $pageSize)
            ]
        ]);
    } catch (Exception $e) {
        Response::serverError('获取条形码列表失败');
    }
}

// 根据条形码号查询
if ($method === 'GET' && isset($_GET['barcode'])) {
    $barcode = trim($_GET['barcode']);

    try {
        $sql = "SELECT b.*, p.name as product_name, p.model as product_model, p.image as product_image
                FROM barcodes b
                LEFT JOIN products p ON b.product_id = p.id
                WHERE b.barcode = :barcode";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':barcode', $barcode);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            Response::success($result);
        } else {
            Response::notFound('条形码不存在');
        }
    } catch (Exception $e) {
        Response::serverError('查询条形码失败');
    }
}

// 获取条形码详情（根据ID）
if ($method === 'GET' && isset($_GET['id'])) {
    $userData = Auth::authenticate();
    $id = (int)$_GET['id'];

    try {
        $sql = "SELECT b.*, p.name as product_name, p.model as product_model
                FROM barcodes b
                LEFT JOIN products p ON b.product_id = p.id
                WHERE b.id = :id";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            Response::success($result);
        } else {
            Response::notFound('条形码不存在');
        }
    } catch (Exception $e) {
        Response::serverError('获取条形码详情失败');
    }
}

// 添加条形码（管理员）
if ($method === 'POST') {
    $userData = Auth::authenticate(true);
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['barcode']) || empty($data['product_id'])) {
        Response::validationError('条形码号和商品ID不能为空');
    }

    $barcode = trim($data['barcode']);

    try {
        // 检查条形码是否已存在
        $checkSql = "SELECT id FROM barcodes WHERE barcode = :barcode";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindParam(':barcode', $barcode);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            Response::error('条形码已存在');
        }

        // 检查商品是否存在
        $productCheckSql = "SELECT id FROM products WHERE id = :product_id";
        $productCheckStmt = $db->prepare($productCheckSql);
        $productCheckStmt->bindParam(':product_id', $data['product_id']);
        $productCheckStmt->execute();

        if ($productCheckStmt->rowCount() === 0) {
            Response::error('商品不存在');
        }

        $sql = "INSERT INTO barcodes (barcode, product_id, stock, location, supplier_code, remark)
                VALUES (:barcode, :product_id, :stock, :location, :supplier_code, :remark)";

        $stmt = $db->prepare($sql);
        $stock = isset($data['stock']) ? (int)$data['stock'] : 0;

        $stmt->bindParam(':barcode', $barcode);
        $stmt->bindParam(':product_id', $data['product_id']);
        $stmt->bindParam(':stock', $stock);
        $stmt->bindParam(':location', $data['location']);
        $stmt->bindParam(':supplier_code', $data['supplier_code']);
        $stmt->bindParam(':remark', $data['remark']);

        if ($stmt->execute()) {
            Response::success(['id' => $db->lastInsertId()], '条形码添加成功');
        } else {
            Response::serverError('条形码添加失败');
        }
    } catch (Exception $e) {
        Response::serverError('条形码添加失败');
    }
}

// 更新条形码（管理员）
if ($method === 'PUT') {
    $userData = Auth::authenticate(true);
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['id'])) {
        Response::validationError('条形码ID不能为空');
    }

    try {
        $updates = [];
        $params = [':id' => $data['id']];

        // 条形码号不允许修改
        $allowedFields = ['product_id', 'stock', 'location', 'supplier_code', 'remark'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($updates)) {
            Response::validationError('没有可更新的字段');
        }

        $sql = "UPDATE barcodes SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        if ($stmt->execute()) {
            Response::success(null, '条形码更新成功');
        } else {
            Response::serverError('条形码更新失败');
        }
    } catch (Exception $e) {
        Response::serverError('条形码更新失败');
    }
}

// 删除条形码（管理员）
if ($method === 'DELETE') {
    $userData = Auth::authenticate(true);

    if (empty($_GET['id'])) {
        Response::validationError('条形码ID不能为空');
    }

    $id = (int)$_GET['id'];

    try {
        // 检查是否有库存
        $checkSql = "SELECT stock FROM barcodes WHERE id = :id";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        $barcode = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$barcode) {
            Response::notFound('条形码不存在');
        }

        if ($barcode['stock'] > 0) {
            Response::error('该条形码存在库存，无法删除');
        }

        $sql = "DELETE FROM barcodes WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            Response::success(null, '条形码删除成功');
        } else {
            Response::serverError('条形码删除失败');
        }
    } catch (Exception $e) {
        Response::serverError('条形码删除失败');
    }
}

Response::notFound('接口不存在');
?&gt;
