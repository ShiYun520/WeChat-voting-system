<?php
/**
 * 统计信息API
 * 
 * 提供活动整体统计数据和排行榜
 */
require_once '../includes/db_config.php';
require_once '../includes/functions.php';

// 设置响应头
header('Content-Type: application/json');

// 路由请求
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'summary':
        getSummaryStats();
        break;
    case 'leaderboard':
        getLeaderboard();
        break;
    case 'countdown':
        getCountdownData();
        break;
    default:
        // 默认返回所有统计数据
        getAllStats();
}

// 获取活动总体统计
function getSummaryStats() {
    $conn = getConnection();
    
    // 获取参与选手数
    $stmt = $conn->prepare("SELECT COUNT(*) FROM contestants WHERE status = 1");
    $stmt->execute();
    $stmt->bind_result($totalContestants);
    $stmt->fetch();
    $stmt->close();
    
    // 获取总投票数
    $stmt = $conn->prepare("SELECT COUNT(*) FROM votes");
    $stmt->execute();
    $stmt->bind_result($totalVotes);
    $stmt->fetch();
    $stmt->close();
    
    // 获取总浏览量
    $stmt = $conn->prepare("SELECT SUM(views) FROM contestants");
    $stmt->execute();
    $stmt->bind_result($totalViews);
    $stmt->fetch();
    $stmt->close();
    
    // 获取今日投票数
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) FROM votes WHERE DATE(created_at) = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $stmt->bind_result($todayVotes);
    $stmt->fetch();
    $stmt->close();
    
    $conn->close();
    
    jsonResponse([
        'success' => true,
        'data' => [
            'totalContestants' => (int)$totalContestants,
            'totalVotes' => (int)$totalVotes,
            'totalViews' => (int)($totalViews ?? 0),
            'todayVotes' => (int)$todayVotes
        ]
    ]);
}

// 获取排行榜数据
function getLeaderboard() {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $limit = min(max($limit, 1), 100); // 限制范围在1-100
    
    $conn = getConnection();
    
    $stmt = $conn->prepare("
        SELECT id, number, name, college, cover_photo, votes, views
        FROM contestants
        WHERE status = 1
        ORDER BY votes DESC, views DESC, id ASC
        LIMIT ?
    ");
    
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $leaderboard = [];
    while ($row = $result->fetch_assoc()) {
        // 从路径中移除前缀并确保URL正确
        if (isset($row['cover_photo']) && !empty($row['cover_photo'])) {
            $row['cover_photo'] = '/' . ltrim($row['cover_photo'], '/');
        }
        $leaderboard[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    jsonResponse([
        'success' => true,
        'data' => $leaderboard
    ]);
}

// 获取倒计时数据
function getCountdownData() {
    $countdown = getCountdown();
    
    jsonResponse([
        'success' => true,
        'data' => $countdown
    ]);
}

// 获取所有统计数据
function getAllStats() {
    $conn = getConnection();
    
    // 基础统计数据
    $summary = [];
    
    // 获取参与选手数
    $stmt = $conn->prepare("SELECT COUNT(*) FROM contestants WHERE status = 1");
    $stmt->execute();
    $stmt->bind_result($summary['totalContestants']);
    $stmt->fetch();
    $stmt->close();
    
    // 获取总投票数
    $stmt = $conn->prepare("SELECT COUNT(*) FROM votes");
    $stmt->execute();
    $stmt->bind_result($summary['totalVotes']);
    $stmt->fetch();
    $stmt->close();
    
    // 获取总浏览量
    $stmt = $conn->prepare("SELECT SUM(views) FROM contestants");
    $stmt->execute();
    $stmt->bind_result($totalViews);
    $stmt->fetch();
    $stmt->close();
    $summary['totalViews'] = (int)($totalViews ?? 0);
    
    // 获取今日投票数
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) FROM votes WHERE DATE(created_at) = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $stmt->bind_result($summary['todayVotes']);
    $stmt->fetch();
    $stmt->close();
    
    // 获取投票趋势（最近7天）
    $trends = [];
    
    // 最近7天的日期
    $dates = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dates[] = $date;
    }
    
    // 获取每天的投票数
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as vote_date, COUNT(*) as vote_count
        FROM votes
        WHERE DATE(created_at) >= ?
        GROUP BY DATE(created_at)
        ORDER BY vote_date ASC
    ");
    
    $sevenDaysAgo = $dates[0];
    $stmt->bind_param("s", $sevenDaysAgo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // 初始化所有日期的投票数为0
    foreach ($dates as $date) {
        $trends[$date] = 0;
    }
    
    // 填充实际数据
    while ($row = $result->fetch_assoc()) {
        $trends[$row['vote_date']] = (int)$row['vote_count'];
    }
    
    $stmt->close();
    
    // 获取前10名选手
    $topContestants = [];
    
    $stmt = $conn->prepare("
        SELECT id, number, name, college, cover_photo, votes
        FROM contestants
        WHERE status = 1
        ORDER BY votes DESC, views DESC, id ASC
        LIMIT 10
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // 从路径中移除前缀并确保URL正确
        if (isset($row['cover_photo']) && !empty($row['cover_photo'])) {
            $row['cover_photo'] = '/' . ltrim($row['cover_photo'], '/');
        }
        $topContestants[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    // 获取倒计时
    $countdown = getCountdown();
    
    // 组合所有数据
    jsonResponse([
        'success' => true,
        'data' => [
            'summary' => $summary,
            'trends' => $trends,
            'topContestants' => $topContestants,
            'countdown' => $countdown
        ]
    ]);
}

// 获取倒计时数据
function getCountdown() {
    $conn = getConnection();
    
    // 从设置表获取结束时间
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'end_time'");
    $stmt->execute();
    $stmt->bind_result($end_time);
    $stmt->fetch();
    $stmt->close();
    
    $conn->close();
    
    if (empty($end_time)) {
        // 默认结束时间为当前时间加30天
        $end_time = date('Y-m-d H:i:s', strtotime('+30 days'));
    }
    
    $now = time();
    $end = strtotime($end_time);
    $distance = $end - $now;
    
    return [
        'endDate' => $end_time,
        'distance' => $distance,
        'days' => floor($distance / (60 * 60 * 24)),
        'hours' => floor(($distance % (60 * 60 * 24)) / (60 * 60)),
        'minutes' => floor(($distance % (60 * 60)) / 60),
        'seconds' => floor($distance % 60)
    ];
}

// JSON响应函数
function jsonResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
