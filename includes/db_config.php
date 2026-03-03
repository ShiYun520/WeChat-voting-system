<?php
// 数据库连接配置
// 注释掉或删除此函数，使用functions.php中的版本
// function getDbConnection() {
//     $host = 'localhost';  // 或您的数据库主机
//     $dbname = 'voting_system';  // 您的数据库名
//     $username = 'tp520';  // 您的数据库用户名
//     $password = 'tp520';  // 您的数据库密码
//     
//     try {
//         $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
//         // 设置PDO错误模式为异常
//         $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//         return $conn;
//     } catch(PDOException $e) {
//         echo "连接失败: " . $e->getMessage();
//         die();
//         require_once('functions.php');
//     }
// }

// 定义数据库常量(如果未定义)
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'voting_system');
if (!defined('DB_USER')) define('DB_USER', 'tp520');
if (!defined('DB_PASS')) define('DB_PASS', 'tp520');

// 获取系统统计数据
function getStats() {
    $conn = getDbConnection();
    
    // 获取选手数
    $stmt = $conn->prepare("SELECT COUNT(*) AS contestant_count FROM contestants WHERE status = 'approved'");
    $stmt->execute();
    $contestant_count = $stmt->fetchColumn();
    
    // 获取总投票数
    $stmt = $conn->prepare("SELECT COUNT(*) AS vote_count FROM votes");
    $stmt->execute();
    $vote_count = $stmt->fetchColumn();
    
    // 获取总浏览量
    $stmt = $conn->prepare("SELECT SUM(views) AS view_count FROM contestants");
    $stmt->execute();
    $view_count = $stmt->fetchColumn() ?? 0;
    
    return [
        'contestant_count' => $contestant_count,
        'vote_count' => $vote_count,
        'view_count' => $view_count
    ];
}

// 获取选手列表
function getContestants($page = 1, $perPage = 10, $search = '') {
    $conn = getDbConnection();
    
    $offset = ($page - 1) * $perPage;
    
    $sql = "
        SELECT c.*, COUNT(v.id) AS votes 
        FROM contestants c
        LEFT JOIN votes v ON c.id = v.contestant_id
        WHERE c.status = 'approved'
    ";
    
    $params = [];
    
    // 添加搜索条件
    if (!empty($search)) {
        $search = "%$search%";
        $sql .= " AND (c.name LIKE ? OR c.number LIKE ?)";
        $params[] = $search;
        $params[] = $search;
    }
    
    $sql .= " GROUP BY c.id ORDER BY votes DESC, c.created_at DESC LIMIT ?, ?";
    $params[] = (int)$offset;
    $params[] = (int)$perPage;
    
    $stmt = $conn->prepare($sql);
    
    // 绑定参数
    for ($i = 0; $i < count($params); $i++) {
        $paramIndex = $i + 1;
        $stmt->bindValue($paramIndex, $params[$i]);
    }
    
    $stmt->execute();
    $contestants = $stmt->fetchAll();
    
    return $contestants;
}
// ... existing code ...

?>
