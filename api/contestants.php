<?php
/**
 * 选手相关API
 * 
 * 提供获取选手列表、选手详情等接口
 */
require_once '../includes/db_config.php';
require_once '../includes/functions.php';

// 设置响应头
header('Content-Type: application/json');

// 路由请求
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // 获取单个选手详情
            getContestant($_GET['id']);
        } else {
            // 获取选手列表
            getContestantsList();
        }
        break;
    case 'POST':
        // 此API不处理选手创建，由upload.php处理
        jsonResponse(['error' => 'Method not allowed'], 405);
        break;
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

// 获取选手列表
function getContestantsList() {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
    $search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
    $sort = isset($_GET['sort']) ? cleanInput($_GET['sort']) : 'votes';
    $order = isset($_GET['order']) ? (strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC') : 'DESC';
    
    $conn = getConnection();
    
    // 计算总数
    $countSql = "SELECT COUNT(*) FROM contestants WHERE status = 1";
    if (!empty($search)) {
        $countSql .= " AND (name LIKE ? OR number LIKE ? OR college LIKE ?)";
    }
    
    $countStmt = $conn->prepare($countSql);
    if (!empty($search)) {
        $searchParam = "%$search%";
        $countStmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
    }
    $countStmt->execute();
    $countStmt->bind_result($totalItems);
    $countStmt->fetch();
    $countStmt->close();
    
    // 计算分页
    $offset = ($page - 1) * $perPage;
    
    // 验证排序字段
    $allowedSortFields = ['votes', 'views', 'created_at', 'name'];
    if (!in_array($sort, $allowedSortFields)) {
        $sort = 'votes';
    }
    
    // 构建查询
    $sql = "
        SELECT id, number, name, college, cover_photo, votes, views, created_at
        FROM contestants
        WHERE status = 1
    ";
    
    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR number LIKE ? OR college LIKE ?)";
    }
    
    $sql .= " ORDER BY $sort $order LIMIT ?, ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($search)) {
        $searchParam = "%$search%";
        $stmt->bind_param("sssii", $searchParam, $searchParam, $searchParam, $offset, $perPage);
    } else {
        $stmt->bind_param("ii", $offset, $perPage);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $contestants = [];
    while ($row = $result->fetch_assoc()) {
        // 格式化日期
        $row['created_at'] = formatDateTime($row['created_at']);
        
        // 从路径中移除前缀并确保URL正确
        if (isset($row['cover_photo']) && !empty($row['cover_photo'])) {
            $row['cover_photo'] = '/' . ltrim($row['cover_photo'], '/');
        }
        
        $contestants[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    // 返回带分页的数据
    $pagination = getPagination($page, $totalItems, $perPage);
    
    jsonResponse([
        'success' => true,
        'data' => $contestants,
        'pagination' => $pagination
    ]);
}

// 获取单个选手详情
function getContestant($id) {
    $id = (int)$id;
    
    if ($id <= 0) {
        jsonResponse(['error' => 'Invalid contestant ID'], 400);
        return;
    }
    
    $conn = getConnection();
    
    // 获取选手基本信息
    $stmt = $conn->prepare("
        SELECT c.id, c.number, c.name, c.college, c.class_name, c.student_id,
               c.message, c.cover_photo, c.votes, c.views, c.created_at
        FROM contestants c
        WHERE c.id = ? AND c.status = 1
    ");
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        jsonResponse(['error' => 'Contestant not found or not approved'], 404);
        return;
    }
    
    $contestant = $result->fetch_assoc();
    $stmt->close();
    
    // 获取选手照片
    $stmt = $conn->prepare("
        SELECT id, photo_path
        FROM contestant_photos
        WHERE contestant_id = ?
        ORDER BY sort_order ASC
    ");
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $photos = [];
    while ($row = $result->fetch_assoc()) {
        // 从路径中移除前缀并确保URL正确
        if (isset($row['photo_path']) && !empty($row['photo_path'])) {
            $row['photo_path'] = '/' . ltrim($row['photo_path'], '/');
        }
        $photos[] = $row;
    }
    
    $contestant['photos'] = $photos;
    $stmt->close();
    
    // 格式化日期
    $contestant['created_at'] = formatDateTime($contestant['created_at']);
    
    // 从路径中移除前缀并确保URL正确
    if (isset($contestant['cover_photo']) && !empty($contestant['cover_photo'])) {
        $contestant['cover_photo'] = '/' . ltrim($contestant['cover_photo'], '/');
    }
    
    $conn->close();
    
    jsonResponse([
        'success' => true,
        'data' => $contestant
    ]);
}
?>
