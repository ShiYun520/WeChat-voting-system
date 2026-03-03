<?php
require_once '../includes/db_config.php';
require_once '../includes/functions.php';

// 验证管理员登录
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 处理操作请求
if (isset($_POST['action'])) {
    $conn = getDbConnection();
    
    if ($_POST['action'] === 'approve' && isset($_POST['contestant_id'])) {
        // 批准选手
        $contestantId = intval($_POST['contestant_id']);
        try {
            $stmt = $conn->prepare("UPDATE contestants SET status = 'approved' WHERE id = ?");
            $stmt->execute([$contestantId]);
            
            $_SESSION['admin_message'] = "选手已批准";
            $_SESSION['admin_message_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['admin_message'] = "操作失败: " . $e->getMessage();
            $_SESSION['admin_message_type'] = "danger";
        }
    }
    elseif ($_POST['action'] === 'reject' && isset($_POST['contestant_id'])) {
        // 拒绝选手
        $contestantId = intval($_POST['contestant_id']);
        try {
            $stmt = $conn->prepare("UPDATE contestants SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$contestantId]);
            
            $_SESSION['admin_message'] = "选手已拒绝";
            $_SESSION['admin_message_type'] = "warning";
        } catch (PDOException $e) {
            $_SESSION['admin_message'] = "操作失败: " . $e->getMessage();
            $_SESSION['admin_message_type'] = "danger";
        }
    }
    elseif ($_POST['action'] === 'delete' && isset($_POST['contestant_id'])) {
        // 删除选手
        $contestantId = intval($_POST['contestant_id']);
        
        // 开始事务
        try {
            $conn->beginTransaction();
            
            // 删除选手的照片记录
            $stmt = $conn->prepare("DELETE FROM contestant_photos WHERE contestant_id = ?");
            $stmt->execute([$contestantId]);
            
            // 删除选手的投票记录
            $stmt = $conn->prepare("DELETE FROM votes WHERE contestant_id = ?");
            $stmt->execute([$contestantId]);
            
            // 删除选手
            $stmt = $conn->prepare("DELETE FROM contestants WHERE id = ?");
            $stmt->execute([$contestantId]);
            
            // 提交事务
            $conn->commit();
            
            $_SESSION['admin_message'] = "选手已删除";
            $_SESSION['admin_message_type'] = "danger";
        } catch (PDOException $e) {
            // 回滚事务
            $conn->rollBack();
            
            $_SESSION['admin_message'] = "删除失败: " . $e->getMessage();
            $_SESSION['admin_message_type'] = "danger";
        }
    }
    
    // 重定向以防止表单重复提交
    header('Location: ' . $_SERVER['PHP_SELF'] . (isset($_GET['status']) ? '?status=' . $_GET['status'] : ''));
    exit;
}

// 获取状态筛选
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$validStatuses = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($status, $validStatuses)) {
    $status = 'all';
}

// 分页参数
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 20;

// 搜索参数
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 构建查询
try {
    $conn = getDbConnection();
    $params = [];

    $sql = "SELECT id, number, name, college, class, student_id, votes, views, status, created_at FROM contestants WHERE 1=1";

    // 添加状态筛选
    if ($status !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $status;
    }

    // 添加搜索条件
    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR number LIKE ? OR college LIKE ? OR student_id LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // 获取总记录数
    $countSql = "SELECT COUNT(*) FROM ($sql) as count_table";
    $stmt = $conn->prepare($countSql);
    $stmt->execute($params);
    $totalRecords = $stmt->fetchColumn();

    // 计算总页数
    $totalPages = ceil($totalRecords / $perPage);
    $page = max(1, min($page, $totalPages > 0 ? $totalPages : 1));
    $offset = ($page - 1) * $perPage;

    // 获取分页记录
    $sql .= " ORDER BY created_at DESC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $perPage;

    $stmt = $conn->prepare($sql);
    
    // PDO不支持在LIMIT中使用问号占位符，需要手动绑定
    for ($i = 0; $i < count($params) - 2; $i++) {
        $stmt->bindValue($i + 1, $params[$i]);
    }
    $stmt->bindValue(count($params) - 1, $offset, PDO::PARAM_INT);
    $stmt->bindValue(count($params), $perPage, PDO::PARAM_INT);
    
    $stmt->execute();
    $contestants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("查询失败: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>选手管理 - 红文之光 照亮童年振兴梦</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .bd-sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            z-index: 1000;
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
            padding-top: 1.5rem;
        }
        .status-badge-pending {
            background-color: #ffc107;
        }
        .status-badge-approved {
            background-color: #198754;
        }
        .status-badge-rejected {
            background-color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- 侧边栏 -->
            <nav class="col-md-3 col-lg-2 bd-sidebar">
                <div class="position-sticky pt-3">
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                        <span>管理系统</span>
                    </h6>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="bi bi-house"></i> 仪表盘
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="contestants.php">
                                <i class="bi bi-people"></i> 选手管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="review.php">
                                <i class="bi bi-check-square"></i> 选手审核
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="config.php">
                                <i class="bi bi-gear"></i> 系统设置
                            </a>
                        </li>
                    </ul>
                    
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                        <span>账号</span>
                    </h6>
                    <ul class="nav flex-column mb-2">
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> 退出登录
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- 主内容区 -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">选手管理</h1>
                </div>

                <?php if (isset($_SESSION['admin_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['admin_message_type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['admin_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php 
                    unset($_SESSION['admin_message']);
                    unset($_SESSION['admin_message_type']);
                endif; 
                ?>

                <!-- 搜索和筛选工具栏 -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <form class="d-flex" method="get">
                            <input type="text" name="search" class="form-control me-2" placeholder="搜索选手..." value="<?php echo htmlspecialchars($search); ?>">
                            <?php if ($status !== 'all'): ?>
                            <input type="hidden" name="status" value="<?php echo $status; ?>">
                            <?php endif; ?>
                            <button type="submit" class="btn btn-outline-primary">搜索</button>
                        </form>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="btn-group" role="group">
                            <a href="?status=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-outline-secondary <?php echo $status === 'all' ? 'active' : ''; ?>">
                                全部
                            </a>
                            <a href="?status=pending<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-outline-warning <?php echo $status === 'pending' ? 'active' : ''; ?>">
                                待审核
                            </a>
                            <a href="?status=approved<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-outline-success <?php echo $status === 'approved' ? 'active' : ''; ?>">
                                已通过
                            </a>
                            <a href="?status=rejected<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-outline-danger <?php echo $status === 'rejected' ? 'active' : ''; ?>">
                                已拒绝
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 选手列表 -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>编号</th>
                                <th>姓名</th>
                                <th>学院</th>
                                <th>班级</th>
                                <th>点赞</th>
                                <th>浏览</th>
                                <th>状态</th>
                                <th>报名时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($contestants)): ?>
                            <tr>
                                <td colspan="9" class="text-center">没有找到符合条件的选手</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($contestants as $contestant): ?>
                            <tr>
                                <td><?php echo $contestant['number']; ?></td>
                                <td><?php echo $contestant['name']; ?></td>
                                <td><?php echo $contestant['college']; ?></td>
                                <td><?php echo $contestant['class']; ?></td>
                                <td><?php echo $contestant['votes']; ?></td>
                                <td><?php echo $contestant['views']; ?></td>
                                <td>
                                    <?php if ($contestant['status'] === 'pending'): ?>
                                    <span class="badge status-badge-pending">待审核</span>
                                    <?php elseif ($contestant['status'] === 'approved'): ?>
                                    <span class="badge status-badge-approved">已通过</span>
                                    <?php else: ?>
                                    <span class="badge status-badge-rejected">已拒绝</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($contestant['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="../detail.php?id=<?php echo $contestant['id']; ?>" target="_blank" class="btn btn-sm btn-outline-info" title="查看">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        
                                        <?php if ($contestant['status'] === 'pending'): ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('确定批准该选手?');">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="contestant_id" value="<?php echo $contestant['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="批准">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        
                                        <form method="post" class="d-inline" onsubmit="return confirm('确定拒绝该选手?');">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="contestant_id" value="<?php echo $contestant['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="拒绝">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <form method="post" class="d-inline" onsubmit="return confirm('确定删除该选手? 此操作不可恢复!');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="contestant_id" value="<?php echo $contestant['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="删除">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 分页 -->
                <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="上一页">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="下一页">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
