<?php
/**
 * 公共函数库
 */
// ... existing code ...

/**
 * 创建并返回数据库连接
 * @return PDO 返回数据库连接对象，连接失败时会终止程序
 */
if (!function_exists('getDbConnection')) {
    function getDbConnection() {
        try {
            $conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            return $conn;
        } catch (PDOException $e) {
            // 记录错误但不显示详细信息（生产环境）
            error_log("数据库连接失败: " . $e->getMessage());
            // 显示友好的错误信息
            die("无法连接到数据库，请稍后再试或联系管理员。");
        }
    }
}

// ... existing code ...

// JSON响应
function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查用户是否已投票
function hasVoted($contestant_id, $ip_address, $device_id = '') {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM votes 
        WHERE contestant_id = ? AND (ip_address = ? OR (device_id != '' AND device_id = ?)) 
        AND DATE(created_at) = CURDATE()
    ");
    
    $stmt->execute([$contestant_id, $ip_address, $device_id]);
    $count = $stmt->fetchColumn();
    
    return $count > 0;
}

// 记录投票
function recordVote($contestant_id, $ip_address, $device_id = '') {
    $conn = getDbConnection();
    
    // 检查是否已投票
    if (hasVoted($contestant_id, $ip_address, $device_id)) {
        return [
            'success' => false, 
            'message' => '您今天已经投过票了，请明天再来！'
        ];
    }
    
    // 开始事务
    $conn->beginTransaction();
    
    try {
        // 记录投票
        $stmt = $conn->prepare("
            INSERT INTO votes (contestant_id, ip_address, device_id, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        $stmt->execute([$contestant_id, $ip_address, $device_id]);
        
        // 更新选手票数
        $stmt = $conn->prepare("
            UPDATE contestants 
            SET votes = votes + 1 
            WHERE id = ?
        ");
        
        $stmt->execute([$contestant_id]);
        
        // 获取更新后的票数
        $stmt = $conn->prepare("
            SELECT votes FROM contestants WHERE id = ?
        ");
        
        $stmt->execute([$contestant_id]);
        $votes = $stmt->fetchColumn();
        
        // 提交事务
        $conn->commit();
        
        return [
            'success' => true, 
            'message' => '投票成功！', 
            'votes' => $votes
        ];
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        
        return [
            'success' => false, 
            'message' => '投票失败，请稍后再试！'
        ];
    }
}

// 增加浏览量
function incrementViews($contestant_id) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("
        UPDATE contestants 
        SET views = views + 1 
        WHERE id = ?
    ");
    
    $stmt->execute([$contestant_id]);
}

// 获取设置值
function getSetting($key, $default = '') {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("
        SELECT setting_value 
        FROM settings 
        WHERE setting_key = ?
    ");
    
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    
    return $value !== false ? $value : $default;
}

// 获取IP地址
function getIPAddress() {
    // 尝试获取各种可能的IP地址
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    return $ip;
}

// 保存反馈
function saveFeedback($name, $contact, $content) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("
        INSERT INTO feedback (name, contact, content, ip_address, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $ip = getIPAddress();
    $result = $stmt->execute([$name, $contact, $content, $ip]);
    
    return $result;
}

// 生成随机字符串
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
// ... existing code ...
?>
