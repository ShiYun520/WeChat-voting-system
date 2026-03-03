<?php
require_once '../includes/db_config.php';
require_once '../includes/functions.php';

// 设置响应类型为JSON
header('Content-Type: application/json');

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '仅支持POST请求']);
    exit;
}

// 获取POST数据
$postData = json_decode(file_get_contents('php://input'), true);
$contestantId = isset($postData['contestant_id']) ? intval($postData['contestant_id']) : 0;

// 验证选手ID
if ($contestantId <= 0) {
    echo json_encode(['success' => false, 'message' => '无效的选手ID']);
    exit;
}

// 获取数据库连接
$conn = getConnection();

// 检查选手是否存在且已审核通过
$stmt = $conn->prepare("SELECT id, name, votes FROM contestants WHERE id = ? AND status = 'approved'");
$stmt->bind_param("i", $contestantId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => '选手不存在或未通过审核']);
    exit;
}

$contestant = $result->fetch_assoc();
$stmt->close();

// 检查活动是否在有效期内
if (!isContestActive()) {
    echo json_encode(['success' => false, 'message' => '投票活动已结束']);
    exit;
}

// 检查用户是否已经给这个选手投过票
if (hasUserVotedToday($contestantId)) {
    echo json_encode(['success' => false, 'message' => '您今天已经给该选手投过票了']);
    exit;
}

// 检查用户今天的投票次数是否达到上限
$currentVotes = getUserVoteCountToday();
$maxVotesPerDay = 3; // 从设置中获取，这里硬编码为3作为示例

// 从设置中获取每天最大投票数
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'votes_per_day'");
$stmt->execute();
$stmt->bind_result($votesPerDay);
$stmt->fetch();
$stmt->close();

if ($votesPerDay) {
    $maxVotesPerDay = intval($votesPerDay);
}

if ($currentVotes >= $maxVotesPerDay) {
    echo json_encode(['success' => false, 'message' => "您今天的投票次数已达上限（{$maxVotesPerDay}次）"]);
    exit;
}

// 记录用户IP和投票信息
$ip = $_SERVER['REMOTE_ADDR'];
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// 开始事务
$conn->begin_transaction();

try {
    // 插入投票记录
    $stmt = $conn->prepare("
        INSERT INTO votes (contestant_id, ip_address, device_id) 
        VALUES (?, ?, ?)
    ");
    
    $deviceId = md5($userAgent); // 简单的设备ID生成，生产环境可能需要更复杂的方法
    $stmt->bind_param("iss", $contestantId, $ip, $deviceId);
    $stmt->execute();
    $stmt->close();
    
    // 更新选手票数
    $stmt = $conn->prepare("UPDATE contestants SET votes = votes + 1 WHERE id = ?");
    $stmt->bind_param("i", $contestantId);
    $stmt->execute();
    $stmt->close();
    
    // 获取更新后的票数
    $stmt = $conn->prepare("SELECT votes FROM contestants WHERE id = ?");
    $stmt->bind_param("i", $contestantId);
    $stmt->execute();
    $stmt->bind_result($newVotes);
    $stmt->fetch();
    $stmt->close();
    
    // 提交事务
    $conn->commit();
    
    // 返回成功结果
    echo json_encode([
        'success' => true,
        'message' => '投票成功！感谢您的支持',
        'newVoteCount' => $newVotes,
        'remainingVotes' => $maxVotesPerDay - ($currentVotes + 1)
    ]);
    
} catch (Exception $e) {
    // 回滚事务
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => '投票失败，请稍后再试: ' . $e->getMessage()
    ]);
}

// 关闭数据库连接
$conn->close();
?>
