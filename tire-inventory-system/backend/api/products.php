&lt;?php
/**
 * 商品管理 API
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$database = new Database();
$db = $database->getConnection();

// 获取商品列表
if ($method === 'GET' && !isset($_GET['id'])) {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $pageSize = isset($_GET['page_size']) ? (int)$_GET['page_size'] : DEFAULT_PAGE_SIZE;
    $offset = ($page - 1) * $pageSize;

    $category1Id = isset($_GET['category1_id']) ? (int)$_GET['category1_id'] : null;
    $category2Id = isset($_GET['category2_id']) ? (int)$_GET['category2_id'] : null;
    $category3Id = isset($_GET['category3_id']) ? (int)$_GET['category3_id'] : null;
    $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : null;

    try {
        // 构建查询条件
        $where = ['p.status = 1'];
        $params = [];

        if ($category1Id) {
            $where[] = 'p.category1_id = :category1_id';
            $params[':category1_id'] = $category1Id;
        }
        if ($category2Id) {
            $where[] = 'p.category2_id = :category2_id';
            $params[':category2_id'] = $category2Id;
        }
        if ($category3Id) {
            $where[] = 'p.category3_id = :category3_id';
            $params[':category3_id'] = $category3Id;
        }
        if ($keyword) {
            $where[] = '(p.name LIKE :keyword OR p.model LIKE :keyword)';
            $params[':keyword'] = "%{$keyword}%";
        }

        $whereClause = implode(' AND ', $where);

        // 查询总数
        $countSql = "SELECT COUNT(*) as total FROM products p WHERE {$whereClause}";
        $countStmt = $db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // 查询商品列表
        $sql = "SELECT p.*,
                c1.name as category1_name,
                c2.name as category2_name,
                c3.name as category3_name,
                COALESCE(SUM(b.stock), 0) as total_stock
                FROM products p
                LEFT JOIN categories_level1 c1 ON p.category1_id = c1.id
                LEFT JOIN categories_level2 c2 ON p.category2_id = c2.id
                LEFT JOIN categories_level3 c3 ON p.category3_id = c3.id
                LEFT JOIN barcodes b ON p.id = b.product_id
                WHERE {$whereClause}
                GROUP BY p.id
                ORDER BY p.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success([
            'list' => $products,
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => ceil($total / $pageSize)
            ]
        ]);
    } catch (Exception $e) {
        Response::serverError('获取商品列表失败');
    }
}

// 获取商品详情
if ($method === 'GET' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    try {
        $sql = "SELECT p.*,
                c1.name as category1_name,
                c2.name as category2_name,
                c3.name as category3_name
                FROM products p
                LEFT JOIN categories_level1 c1 ON p.category1_id = c1.id
                LEFT JOIN categories_level2 c2 ON p.category2_id = c2.id
                LEFT JOIN categories_level3 c3 ON p.category3_id = c3.id
                WHERE p.id = :id";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            Response::notFound('商品不存在');
        }

        // 获取关联的条形码
        $barcodeSql = "SELECT * FROM barcodes WHERE product_id = :product_id ORDER BY created_at DESC";
        $barcodeStmt = $db->prepare($barcodeSql);
        $barcodeStmt->bindParam(':product_id', $id);
        $barcodeStmt->execute();
        $product['barcodes'] = $barcodeStmt->fetchAll(PDO::FETCH_ASSOC);

        // 计算总库存
        $product['total_stock'] = array_sum(array_column($product['barcodes'], 'stock'));

        Response::success($product);
    } catch (Exception $e) {
        Response::serverError('获取商品详情失败');
    }
}

// 添加商品（管理员）
if ($method === 'POST') {
    $userData = Auth::authenticate(true);
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['name']) || empty($data['model'])) {
        Response::validationError('商品名称和型号不能为空');
    }

    if (empty($data['category1_id']) || empty($data['category2_id']) || empty($data['category3_id'])) {
        Response::validationError('请选择完整的商品分类');
    }

    try {
        $sql = "INSERT INTO products (name, model, category1_id, category2_id, category3_id, price, image, description)
                VALUES (:name, :model, :category1_id, :category2_id, :category3_id, :price, :image, :description)";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':model', $data['model']);
        $stmt->bindParam(':category1_id', $data['category1_id']);
        $stmt->bindParam(':category2_id', $data['category2_id']);
        $stmt->bindParam(':category3_id', $data['category3_id']);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':image', $data['image']);
        $stmt->bindParam(':description', $data['description']);

        if ($stmt->execute()) {
            Response::success(['id' => $db->lastInsertId()], '商品添加成功');
        } else {
            Response::serverError('商品添加失败');
        }
    } catch (Exception $e) {
        Response::serverError('商品添加失败');
    }
}

// 更新商品（管理员）
if ($method === 'PUT') {
    $userData = Auth::authenticate(true);
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['id'])) {
        Response::validationError('商品ID不能为空');
    }

    try {
        $updates = [];
        $params = [':id' => $data['id']];

        $allowedFields = ['name', 'model', 'category1_id', 'category2_id', 'category3_id', 'price', 'image', 'description', 'status'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($updates)) {
            Response::validationError('没有可更新的字段');
        }

        $sql = "UPDATE products SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        if ($stmt->execute()) {
            Response::success(null, '商品更新成功');
        } else {
            Response::serverError('商品更新失败');
        }
    } catch (Exception $e) {
        Response::serverError('商品更新失败');
    }
}

// 删除商品（管理员）
if ($method === 'DELETE') {
    $userData = Auth::authenticate(true);

    if (empty($_GET['id'])) {
        Response::validationError('商品ID不能为空');
    }

    $id = (int)$_GET['id'];

    try {
        // 检查是否有关联的条形码
        $checkSql = "SELECT COUNT(*) as count FROM barcodes WHERE product_id = :id";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        $count = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($count > 0) {
            Response::error('该商品存在关联的条形码，无法删除');
        }

        $sql = "DELETE FROM products WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            Response::success(null, '商品删除成功');
        } else {
            Response::serverError('商品删除失败');
        }
    } catch (Exception $e) {
        Response::serverError('商品删除失败');
    }
}

Response::notFound('接口不存在');
?&gt;
