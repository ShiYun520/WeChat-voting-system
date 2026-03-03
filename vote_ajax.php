<?php
// 确保没有之前的输出
ob_clean();

// 启用session支持
session_start();

// 设置错误处理
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/vote_error.log');

// 设置内容类型
header('Content-Type: application/json; charset=utf-8');

// 简单的数据库连接函数
function getConnection() {
    $conn = new mysqli('localhost', 'tp520', 'tp520', 'voting_system');
    // 设置字符集
    $conn->set_charset('utf8mb4');
    return $conn;
}

// 捕获所有可能的异常和错误
try {
    // 连接数据库
    $conn = getConnection();
    if ($conn->connect_error) {
        throw new Exception("数据库连接失败: " . $conn->connect_error);
    }
    
    // 检查是否为POST请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("只接受POST请求");
    }
    
    // 获取选手ID
    $contestantId = isset($_POST['contestant_id']) ? (int)$_POST['contestant_id'] : 0;
    if ($contestantId <= 0) {
        throw new Exception("选手ID无效");
    }
    
    // 获取IP地址
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // 检查选手是否存在
    $stmt = $conn->prepare("SELECT id FROM contestants WHERE id = ?");
    if (!$stmt) {
        throw new Exception("数据库查询错误: " . $conn->error);
    }
    
    $stmt->bind_param("i", $contestantId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("选手不存在");
    }
    $stmt->close();
    
    // 检查是否已经投票
    $stmt = $conn->prepare("SELECT id FROM votes WHERE contestant_id = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)");
    if (!$stmt) {
        throw new Exception("数据库查询错误: " . $conn->error);
    }
    
    $stmt->bind_param("is", $contestantId, $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        throw new Exception("您今天已经为该选手点赞过了");
    }
    $stmt->close();
    
// ... existing code ...

// 添加投票记录 - 移除vote_date生成列
// ... existing code ...

// 添加投票记录 - 修正参数绑定
$stmt = $conn->prepare("INSERT INTO votes (contestant_id, ip_address, created_at, vote_value, user_id, session_id, vote_source, device_id) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)");
if (!$stmt) {
    throw new Exception("数据库操作准备失败: " . $conn->error);
}

$voteValue = 1; // 默认投票值为1
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$sessionId = session_id(); // 获取当前会话ID
$voteSource = isset($_POST['source']) ? $_POST['source'] : 'website';
$deviceId = isset($_POST['device_id']) ? $_POST['device_id'] : null;

// 修正类型字符串 - "isiisss" 对应七个参数
$stmt->bind_param("isiisss", $contestantId, $ip, $voteValue, $userId, $sessionId, $voteSource, $deviceId);

if (!$stmt->execute()) {
    throw new Exception("投票失败，请稍后再试: " . $stmt->error);
}



    
    // 成功响应
    echo json_encode([
        'success' => true,
        'message' => '点赞成功！'
    ]);
    
} catch (Exception $e) {
    // 记录错误
    error_log("Vote Error: " . $e->getMessage());
    
    // 返回错误响应
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
} finally {
    // 关闭连接
    if (isset($conn) && $conn) {
        $conn->close();
    }
}

// 确保没有尾随输出
exit();
?>
