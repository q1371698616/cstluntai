&lt;?php
/**
 * 批量导入商品 API
 * 支持文本识别批量添加商品
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$database = new Database();
$db = $database->getConnection();

// 批量导入商品（管理员）
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'parse-text') {
    $userData = Auth::authenticate(true); // 需要管理员权限
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['text'])) {
        Response::validationError('导入文本不能为空');
    }

    $text = $data['text'];
    $autoCreate = isset($data['auto_create']) ? (bool)$data['auto_create'] : false;

    try {
        $parsedData = parseProductText($text);

        if ($autoCreate) {
            // 自动创建商品
            $result = createProductsFromParsedData($db, $parsedData);
            Response::success($result, '批量导入完成');
        } else {
            // 仅返回解析结果，不创建
            Response::success([
                'parsed_count' => count($parsedData),
                'products' => $parsedData
            ], '文本解析成功');
        }
    } catch (Exception $e) {
        Response::serverError('批量导入失败: ' . $e->getMessage());
    }
}

// 确认导入（管理员）
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'import') {
    $userData = Auth::authenticate(true);
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['products']) || !is_array($data['products'])) {
        Response::validationError('商品数据不能为空');
    }

    try {
        $result = createProductsFromParsedData($db, $data['products']);
        Response::success($result, '批量导入完成');
    } catch (Exception $e) {
        Response::serverError('批量导入失败: ' . $e->getMessage());
    }
}

/**
 * 解析商品文本
 * 格式示例：
 * 135/70R12 万达 120元
 * 145/70R12 玲珑130元 朝阳 130元 正新 170元
 * 165R14 三角 230元 玲珑 210元
 */
function parseProductText($text) {
    $lines = explode("\n", $text);
    $products = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }

        // 提取轮胎规格（如 135/70R12, 165R14, 155R13）
        if (preg_match('/^(\d+(?:\/\d+)?R\d+)(.+)$/', $line, $matches)) {
            $spec = trim($matches[1]); // 规格，如 135/70R12
            $rest = trim($matches[2]); // 剩余部分

            // 提取一级分类（轮胎直径，如 R12, R13, R14）
            preg_match('/R(\d+)/', $spec, $sizeMatch);
            $category1 = 'R' . $sizeMatch[1]; // 如 R12

            // 提取品牌和价格
            // 格式：品牌 价格元 品牌 价格元...
            preg_match_all('/([^\d\s]+)\s*(\d+)\s*元/', $rest, $brandMatches, PREG_SET_ORDER);

            foreach ($brandMatches as $match) {
                $brand = trim($match[1]); // 品牌名
                $price = trim($match[2]); // 价格

                $products[] = [
                    'spec' => $spec,           // 规格（二级、三级分类都用这个）
                    'category1' => $category1, // 一级分类（R12, R13等）
                    'brand' => $brand,         // 品牌
                    'price' => floatval($price),
                    'name' => $spec . ' ' . $brand, // 商品名
                    'model' => $spec,          // 型号
                ];
            }
        }
    }

    return $products;
}

/**
 * 从解析的数据创建商品
 */
function createProductsFromParsedData($db, $products) {
    $successCount = 0;
    $failedCount = 0;
    $failedItems = [];
    $createdCategories = []; // 缓存已创建的分类

    $db->beginTransaction();

    try {
        foreach ($products as $product) {
            try {
                // 1. 获取或创建一级分类（如 R12, R13, R14）
                $category1Id = getOrCreateCategory1($db, $product['category1'], $createdCategories);

                // 2. 获取或创建二级分类（规格，如 135/70R12）
                $category2Id = getOrCreateCategory2($db, $category1Id, $product['spec'], $createdCategories);

                // 3. 获取或创建三级分类（也是规格）
                $category3Id = getOrCreateCategory3($db, $category2Id, $product['spec'], $createdCategories);

                // 4. 检查商品是否已存在
                $checkSql = "SELECT id FROM products WHERE name = :name AND model = :model";
                $checkStmt = $db->prepare($checkSql);
                $checkStmt->bindParam(':name', $product['name']);
                $checkStmt->bindParam(':model', $product['model']);
                $checkStmt->execute();

                if ($checkStmt->rowCount() > 0) {
                    // 商品已存在，跳过
                    $failedItems[] = [
                        'product' => $product['name'],
                        'reason' => '商品已存在'
                    ];
                    $failedCount++;
                    continue;
                }

                // 5. 创建商品
                $insertSql = "INSERT INTO products (name, model, category1_id, category2_id, category3_id, price, status)
                              VALUES (:name, :model, :category1_id, :category2_id, :category3_id, :price, 1)";
                $insertStmt = $db->prepare($insertSql);
                $insertStmt->bindParam(':name', $product['name']);
                $insertStmt->bindParam(':model', $product['model']);
                $insertStmt->bindParam(':category1_id', $category1Id);
                $insertStmt->bindParam(':category2_id', $category2Id);
                $insertStmt->bindParam(':category3_id', $category3Id);
                $insertStmt->bindParam(':price', $product['price']);

                if ($insertStmt->execute()) {
                    $successCount++;
                } else {
                    $failedItems[] = [
                        'product' => $product['name'],
                        'reason' => '创建失败'
                    ];
                    $failedCount++;
                }
            } catch (Exception $e) {
                $failedItems[] = [
                    'product' => $product['name'],
                    'reason' => $e->getMessage()
                ];
                $failedCount++;
            }
        }

        $db->commit();

        return [
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'failed_items' => $failedItems,
            'total' => count($products)
        ];
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * 获取或创建一级分类
 */
function getOrCreateCategory1($db, $name, &$cache) {
    $cacheKey = 'cat1_' . $name;

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    // 查询是否存在
    $sql = "SELECT id FROM categories_level1 WHERE name = :name";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':name', $name);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $id = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
        $cache[$cacheKey] = $id;
        return $id;
    }

    // 不存在则创建
    $insertSql = "INSERT INTO categories_level1 (name, sort_order, status) VALUES (:name, 0, 1)";
    $insertStmt = $db->prepare($insertSql);
    $insertStmt->bindParam(':name', $name);
    $insertStmt->execute();

    $id = $db->lastInsertId();
    $cache[$cacheKey] = $id;
    return $id;
}

/**
 * 获取或创建二级分类
 */
function getOrCreateCategory2($db, $parentId, $name, &$cache) {
    $cacheKey = 'cat2_' . $parentId . '_' . $name;

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    // 查询是否存在
    $sql = "SELECT id FROM categories_level2 WHERE parent_id = :parent_id AND name = :name";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':parent_id', $parentId);
    $stmt->bindParam(':name', $name);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $id = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
        $cache[$cacheKey] = $id;
        return $id;
    }

    // 不存在则创建
    $insertSql = "INSERT INTO categories_level2 (parent_id, name, sort_order, status)
                  VALUES (:parent_id, :name, 0, 1)";
    $insertStmt = $db->prepare($insertSql);
    $insertStmt->bindParam(':parent_id', $parentId);
    $insertStmt->bindParam(':name', $name);
    $insertStmt->execute();

    $id = $db->lastInsertId();
    $cache[$cacheKey] = $id;
    return $id;
}

/**
 * 获取或创建三级分类
 */
function getOrCreateCategory3($db, $parentId, $name, &$cache) {
    $cacheKey = 'cat3_' . $parentId . '_' . $name;

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    // 查询是否存在
    $sql = "SELECT id FROM categories_level3 WHERE parent_id = :parent_id AND name = :name";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':parent_id', $parentId);
    $stmt->bindParam(':name', $name);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $id = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
        $cache[$cacheKey] = $id;
        return $id;
    }

    // 不存在则创建
    $insertSql = "INSERT INTO categories_level3 (parent_id, name, sort_order, status)
                  VALUES (:parent_id, :name, 0, 1)";
    $insertStmt = $db->prepare($insertSql);
    $insertStmt->bindParam(':parent_id', $parentId);
    $insertStmt->bindParam(':name', $name);
    $insertStmt->execute();

    $id = $db->lastInsertId();
    $cache[$cacheKey] = $id;
    return $id;
}

Response::notFound('接口不存在');
?&gt;
