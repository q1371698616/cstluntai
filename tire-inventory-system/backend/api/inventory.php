&lt;?php
/**
 * 入库/出库操作 API
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$database = new Database();
$db = $database->getConnection();

// 入库操作
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'inbound') {
    $userData = Auth::authenticate();
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['barcode']) || empty($data['quantity'])) {
        Response::validationError('条形码和数量不能为空');
    }

    $barcode = trim($data['barcode']);
    $quantity = (int)$data['quantity'];

    if ($quantity <= 0) {
        Response::validationError('入库数量必须大于0');
    }

    try {
        $db->beginTransaction();

        // 查询条形码信息
        $checkSql = "SELECT id, product_id, stock FROM barcodes WHERE barcode = :barcode FOR UPDATE";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindParam(':barcode', $barcode);
        $checkStmt->execute();

        if ($checkStmt->rowCount() === 0) {
            $db->rollBack();
            Response::notFound('条形码不存在');
        }

        $barcodeInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);

        // 更新库存
        $newStock = $barcodeInfo['stock'] + $quantity;
        $updateSql = "UPDATE barcodes SET stock = :stock, last_inbound_time = NOW() WHERE id = :id";
        $updateStmt = $db->prepare($updateSql);
        $updateStmt->bindParam(':stock', $newStock);
        $updateStmt->bindParam(':id', $barcodeInfo['id']);
        $updateStmt->execute();

        // 记录入库
        $recordSql = "INSERT INTO inbound_records (barcode_id, barcode, product_id, quantity, operator_id, operator_name, remark)
                      VALUES (:barcode_id, :barcode, :product_id, :quantity, :operator_id, :operator_name, :remark)";
        $recordStmt = $db->prepare($recordSql);
        $recordStmt->bindParam(':barcode_id', $barcodeInfo['id']);
        $recordStmt->bindParam(':barcode', $barcode);
        $recordStmt->bindParam(':product_id', $barcodeInfo['product_id']);
        $recordStmt->bindParam(':quantity', $quantity);
        $recordStmt->bindParam(':operator_id', $userData['user_id']);
        $recordStmt->bindParam(':operator_name', $userData['username']);
        $recordStmt->bindParam(':remark', $data['remark']);
        $recordStmt->execute();

        $db->commit();

        Response::success([
            'barcode_id' => $barcodeInfo['id'],
            'record_id' => $db->lastInsertId(),
            'new_stock' => $newStock
        ], '入库成功');
    } catch (Exception $e) {
        $db->rollBack();
        Response::serverError('入库操作失败');
    }
}

// 出库操作
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'outbound') {
    $userData = Auth::authenticate();
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['barcode']) || empty($data['quantity'])) {
        Response::validationError('条形码和数量不能为空');
    }

    $barcode = trim($data['barcode']);
    $quantity = (int)$data['quantity'];

    if ($quantity <= 0) {
        Response::validationError('出库数量必须大于0');
    }

    try {
        $db->beginTransaction();

        // 查询条形码信息
        $checkSql = "SELECT id, product_id, stock FROM barcodes WHERE barcode = :barcode FOR UPDATE";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindParam(':barcode', $barcode);
        $checkStmt->execute();

        if ($checkStmt->rowCount() === 0) {
            $db->rollBack();
            Response::notFound('条形码不存在');
        }

        $barcodeInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);

        // 检查库存是否充足
        if ($barcodeInfo['stock'] < $quantity) {
            $db->rollBack();
            Response::error('库存不足，当前库存：' . $barcodeInfo['stock']);
        }

        // 更新库存
        $newStock = $barcodeInfo['stock'] - $quantity;
        $updateSql = "UPDATE barcodes SET stock = :stock, last_outbound_time = NOW() WHERE id = :id";
        $updateStmt = $db->prepare($updateSql);
        $updateStmt->bindParam(':stock', $newStock);
        $updateStmt->bindParam(':id', $barcodeInfo['id']);
        $updateStmt->execute();

        // 记录出库
        $recordSql = "INSERT INTO outbound_records
                      (barcode_id, barcode, product_id, quantity, license_plate, license_plate_image, operator_id, operator_name, remark)
                      VALUES (:barcode_id, :barcode, :product_id, :quantity, :license_plate, :license_plate_image, :operator_id, :operator_name, :remark)";
        $recordStmt = $db->prepare($recordSql);
        $recordStmt->bindParam(':barcode_id', $barcodeInfo['id']);
        $recordStmt->bindParam(':barcode', $barcode);
        $recordStmt->bindParam(':product_id', $barcodeInfo['product_id']);
        $recordStmt->bindParam(':quantity', $quantity);
        $recordStmt->bindParam(':license_plate', $data['license_plate']);
        $recordStmt->bindParam(':license_plate_image', $data['license_plate_image']);
        $recordStmt->bindParam(':operator_id', $userData['user_id']);
        $recordStmt->bindParam(':operator_name', $userData['username']);
        $recordStmt->bindParam(':remark', $data['remark']);
        $recordStmt->execute();

        $db->commit();

        Response::success([
            'barcode_id' => $barcodeInfo['id'],
            'record_id' => $db->lastInsertId(),
            'new_stock' => $newStock
        ], '出库成功');
    } catch (Exception $e) {
        $db->rollBack();
        Response::serverError('出库操作失败');
    }
}

// 批量入库
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'batch-inbound') {
    $userData = Auth::authenticate();
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['items']) || !is_array($data['items'])) {
        Response::validationError('批量入库数据不能为空');
    }

    try {
        $db->beginTransaction();

        $successCount = 0;
        $failedItems = [];

        foreach ($data['items'] as $item) {
            if (empty($item['barcode']) || empty($item['quantity'])) {
                $failedItems[] = [
                    'barcode' => $item['barcode'] ?? '',
                    'reason' => '条形码或数量为空'
                ];
                continue;
            }

            $barcode = trim($item['barcode']);
            $quantity = (int)$item['quantity'];

            if ($quantity <= 0) {
                $failedItems[] = [
                    'barcode' => $barcode,
                    'reason' => '数量必须大于0'
                ];
                continue;
            }

            // 查询条形码
            $checkSql = "SELECT id, product_id, stock FROM barcodes WHERE barcode = :barcode FOR UPDATE";
            $checkStmt = $db->prepare($checkSql);
            $checkStmt->bindParam(':barcode', $barcode);
            $checkStmt->execute();

            if ($checkStmt->rowCount() === 0) {
                $failedItems[] = [
                    'barcode' => $barcode,
                    'reason' => '条形码不存在'
                ];
                continue;
            }

            $barcodeInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);

            // 更新库存
            $newStock = $barcodeInfo['stock'] + $quantity;
            $updateSql = "UPDATE barcodes SET stock = :stock, last_inbound_time = NOW() WHERE id = :id";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->bindParam(':stock', $newStock);
            $updateStmt->bindParam(':id', $barcodeInfo['id']);
            $updateStmt->execute();

            // 记录入库
            $recordSql = "INSERT INTO inbound_records (barcode_id, barcode, product_id, quantity, operator_id, operator_name, remark)
                          VALUES (:barcode_id, :barcode, :product_id, :quantity, :operator_id, :operator_name, :remark)";
            $recordStmt = $db->prepare($recordSql);
            $remark = isset($item['remark']) ? $item['remark'] : null;
            $recordStmt->bindParam(':barcode_id', $barcodeInfo['id']);
            $recordStmt->bindParam(':barcode', $barcode);
            $recordStmt->bindParam(':product_id', $barcodeInfo['product_id']);
            $recordStmt->bindParam(':quantity', $quantity);
            $recordStmt->bindParam(':operator_id', $userData['user_id']);
            $recordStmt->bindParam(':operator_name', $userData['username']);
            $recordStmt->bindParam(':remark', $remark);
            $recordStmt->execute();

            $successCount++;
        }

        $db->commit();

        Response::success([
            'success_count' => $successCount,
            'failed_count' => count($failedItems),
            'failed_items' => $failedItems
        ], "批量入库完成，成功 {$successCount} 条");
    } catch (Exception $e) {
        $db->rollBack();
        Response::serverError('批量入库操作失败');
    }
}

// 批量出库
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'batch-outbound') {
    $userData = Auth::authenticate();
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['items']) || !is_array($data['items'])) {
        Response::validationError('批量出库数据不能为空');
    }

    $licensePlate = isset($data['license_plate']) ? trim($data['license_plate']) : null;
    $licensePlateImage = isset($data['license_plate_image']) ? trim($data['license_plate_image']) : null;

    try {
        $db->beginTransaction();

        $successCount = 0;
        $failedItems = [];

        foreach ($data['items'] as $item) {
            if (empty($item['barcode']) || empty($item['quantity'])) {
                $failedItems[] = [
                    'barcode' => $item['barcode'] ?? '',
                    'reason' => '条形码或数量为空'
                ];
                continue;
            }

            $barcode = trim($item['barcode']);
            $quantity = (int)$item['quantity'];

            if ($quantity <= 0) {
                $failedItems[] = [
                    'barcode' => $barcode,
                    'reason' => '数量必须大于0'
                ];
                continue;
            }

            // 查询条形码
            $checkSql = "SELECT id, product_id, stock FROM barcodes WHERE barcode = :barcode FOR UPDATE";
            $checkStmt = $db->prepare($checkSql);
            $checkStmt->bindParam(':barcode', $barcode);
            $checkStmt->execute();

            if ($checkStmt->rowCount() === 0) {
                $failedItems[] = [
                    'barcode' => $barcode,
                    'reason' => '条形码不存在'
                ];
                continue;
            }

            $barcodeInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);

            // 检查库存
            if ($barcodeInfo['stock'] < $quantity) {
                $failedItems[] = [
                    'barcode' => $barcode,
                    'reason' => "库存不足，当前库存：{$barcodeInfo['stock']}"
                ];
                continue;
            }

            // 更新库存
            $newStock = $barcodeInfo['stock'] - $quantity;
            $updateSql = "UPDATE barcodes SET stock = :stock, last_outbound_time = NOW() WHERE id = :id";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->bindParam(':stock', $newStock);
            $updateStmt->bindParam(':id', $barcodeInfo['id']);
            $updateStmt->execute();

            // 记录出库
            $recordSql = "INSERT INTO outbound_records
                          (barcode_id, barcode, product_id, quantity, license_plate, license_plate_image, operator_id, operator_name, remark)
                          VALUES (:barcode_id, :barcode, :product_id, :quantity, :license_plate, :license_plate_image, :operator_id, :operator_name, :remark)";
            $recordStmt = $db->prepare($recordSql);
            $remark = isset($item['remark']) ? $item['remark'] : null;
            $recordStmt->bindParam(':barcode_id', $barcodeInfo['id']);
            $recordStmt->bindParam(':barcode', $barcode);
            $recordStmt->bindParam(':product_id', $barcodeInfo['product_id']);
            $recordStmt->bindParam(':quantity', $quantity);
            $recordStmt->bindParam(':license_plate', $licensePlate);
            $recordStmt->bindParam(':license_plate_image', $licensePlateImage);
            $recordStmt->bindParam(':operator_id', $userData['user_id']);
            $recordStmt->bindParam(':operator_name', $userData['username']);
            $recordStmt->bindParam(':remark', $remark);
            $recordStmt->execute();

            $successCount++;
        }

        $db->commit();

        Response::success([
            'success_count' => $successCount,
            'failed_count' => count($failedItems),
            'failed_items' => $failedItems
        ], "批量出库完成，成功 {$successCount} 条");
    } catch (Exception $e) {
        $db->rollBack();
        Response::serverError('批量出库操作失败');
    }
}

Response::notFound('接口不存在');
?&gt;
