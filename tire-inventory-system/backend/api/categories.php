&lt;?php
/**
 * 分类管理 API
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$database = new Database();
$db = $database->getConnection();

// 获取所有分类（树形结构）
if ($method === 'GET' && !isset($_GET['level'])) {
    try {
        // 获取一级分类
        $sql1 = "SELECT * FROM categories_level1 WHERE status = 1 ORDER BY sort_order ASC, id ASC";
        $stmt1 = $db->prepare($sql1);
        $stmt1->execute();
        $level1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        foreach ($level1 as &$cat1) {
            // 获取二级分类
            $sql2 = "SELECT * FROM categories_level2 WHERE parent_id = :parent_id AND status = 1 ORDER BY sort_order ASC, id ASC";
            $stmt2 = $db->prepare($sql2);
            $stmt2->bindParam(':parent_id', $cat1['id']);
            $stmt2->execute();
            $level2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            foreach ($level2 as &$cat2) {
                // 获取三级分类
                $sql3 = "SELECT * FROM categories_level3 WHERE parent_id = :parent_id AND status = 1 ORDER BY sort_order ASC, id ASC";
                $stmt3 = $db->prepare($sql3);
                $stmt3->bindParam(':parent_id', $cat2['id']);
                $stmt3->execute();
                $cat2['children'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);
            }

            $cat1['children'] = $level2;
        }

        Response::success($level1);
    } catch (Exception $e) {
        Response::serverError('获取分类失败');
    }
}

// 获取指定级别的分类
if ($method === 'GET' && isset($_GET['level'])) {
    $level = (int)$_GET['level'];
    $parentId = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : null;

    try {
        if ($level === 1) {
            $sql = "SELECT * FROM categories_level1 WHERE status = 1 ORDER BY sort_order ASC, id ASC";
            $stmt = $db->prepare($sql);
        } elseif ($level === 2 && $parentId) {
            $sql = "SELECT * FROM categories_level2 WHERE parent_id = :parent_id AND status = 1 ORDER BY sort_order ASC, id ASC";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':parent_id', $parentId);
        } elseif ($level === 3 && $parentId) {
            $sql = "SELECT * FROM categories_level3 WHERE parent_id = :parent_id AND status = 1 ORDER BY sort_order ASC, id ASC";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':parent_id', $parentId);
        } else {
            Response::validationError('参数错误');
        }

        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Response::success($categories);
    } catch (Exception $e) {
        Response::serverError('获取分类失败');
    }
}

// 添加分类（管理员）
if ($method === 'POST') {
    $userData = Auth::authenticate(true); // 需要管理员权限
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['name']) || empty($data['level'])) {
        Response::validationError('分类名称和级别不能为空');
    }

    $level = (int)$data['level'];
    $name = trim($data['name']);
    $sortOrder = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;

    try {
        if ($level === 1) {
            $sql = "INSERT INTO categories_level1 (name, sort_order) VALUES (:name, :sort_order)";
            $stmt = $db->prepare($sql);
        } elseif ($level === 2) {
            if (empty($data['parent_id'])) {
                Response::validationError('二级分类必须指定上级分类');
            }
            $sql = "INSERT INTO categories_level2 (parent_id, name, sort_order) VALUES (:parent_id, :name, :sort_order)";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':parent_id', $data['parent_id']);
        } elseif ($level === 3) {
            if (empty($data['parent_id'])) {
                Response::validationError('三级分类必须指定上级分类');
            }
            $sql = "INSERT INTO categories_level3 (parent_id, name, sort_order) VALUES (:parent_id, :name, :sort_order)";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':parent_id', $data['parent_id']);
        } else {
            Response::validationError('分类级别错误');
        }

        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':sort_order', $sortOrder);

        if ($stmt->execute()) {
            Response::success(['id' => $db->lastInsertId()], '分类添加成功');
        } else {
            Response::serverError('分类添加失败');
        }
    } catch (Exception $e) {
        Response::serverError('分类添加失败');
    }
}

// 更新分类（管理员）
if ($method === 'PUT') {
    $userData = Auth::authenticate(true);
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['id']) || empty($data['level'])) {
        Response::validationError('分类ID和级别不能为空');
    }

    $level = (int)$data['level'];
    $id = (int)$data['id'];
    $name = isset($data['name']) ? trim($data['name']) : null;
    $sortOrder = isset($data['sort_order']) ? (int)$data['sort_order'] : null;
    $status = isset($data['status']) ? (int)$data['status'] : null;

    try {
        $table = $level === 1 ? 'categories_level1' : ($level === 2 ? 'categories_level2' : 'categories_level3');
        $updates = [];
        $params = [':id' => $id];

        if ($name !== null) {
            $updates[] = "name = :name";
            $params[':name'] = $name;
        }
        if ($sortOrder !== null) {
            $updates[] = "sort_order = :sort_order";
            $params[':sort_order'] = $sortOrder;
        }
        if ($status !== null) {
            $updates[] = "status = :status";
            $params[':status'] = $status;
        }

        if (empty($updates)) {
            Response::validationError('没有可更新的字段');
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        if ($stmt->execute()) {
            Response::success(null, '分类更新成功');
        } else {
            Response::serverError('分类更新失败');
        }
    } catch (Exception $e) {
        Response::serverError('分类更新失败');
    }
}

// 删除分类（管理员）
if ($method === 'DELETE') {
    $userData = Auth::authenticate(true);

    if (empty($_GET['id']) || empty($_GET['level'])) {
        Response::validationError('分类ID和级别不能为空');
    }

    $level = (int)$_GET['level'];
    $id = (int)$_GET['id'];

    try {
        $table = $level === 1 ? 'categories_level1' : ($level === 2 ? 'categories_level2' : 'categories_level3');
        $sql = "DELETE FROM {$table} WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            Response::success(null, '分类删除成功');
        } else {
            Response::serverError('分类删除失败');
        }
    } catch (Exception $e) {
        Response::serverError('分类删除失败');
    }
}

Response::notFound('接口不存在');
?&gt;
