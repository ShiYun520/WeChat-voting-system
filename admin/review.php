<?php
require_once '../includes/db_config.php';
require_once '../includes/functions.php';

// 验证管理员登录
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 处理审核操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['contestant_id'])) {
        $contestantId = intval($_POST['contestant_id']);
        $action = $_POST['action'];
        $conn = getDbConnection();
        
        if ($action === 'approve') {
            // 批准选手
            try {
                $stmt = $conn->prepare("UPDATE contestants SET status = 'approved' WHERE id = ? AND status = 'pending'");
                $stmt->execute([$contestantId]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['admin_message'] = "选手已通过审核";
                    $_SESSION['admin_message_type'] = "success";
                } else {
                    $_SESSION['admin_message'] = "操作失败，选手不存在或已被处理";
                    $_SESSION['admin_message_type'] = "danger";
                }
            } catch (PDOException $e) {
                $_SESSION['admin_message'] = "数据库查询失败: " . $e->getMessage();
                $_SESSION['admin_message_type'] = "danger";
            }
        } elseif ($action === 'reject') {
            // 拒绝选手
            try {
                $stmt = $conn->prepare("UPDATE contestants SET status = 'rejected' WHERE id = ? AND status = 'pending'");
                $stmt->execute([$contestantId]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['admin_message'] = "选手已被拒绝";
                    $_SESSION['admin_message_type'] = "warning";
                } else {
                    $_SESSION['admin_message'] = "操作失败，选手不存在或已被处理";
                    $_SESSION['admin_message_type'] = "danger";
                }
            } catch (PDOException $e) {
                $_SESSION['admin_message'] = "数据库查询失败: " . $e->getMessage();
                $_SESSION['admin_message_type'] = "danger";
            }
        }
    }
    
    // 重定向以防止表单重复提交
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 获取待审核的选手
$pendingContestants = [];
try {
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT id, number, name, college, class, student_id, message, cover_photo, created_at
        FROM contestants
        WHERE status = 'pending'
        ORDER BY created_at ASC
    ");
    $stmt->execute();
    $pendingContestants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "查询失败: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>选手审核 - 红文之光 照亮童年振兴梦</title>
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
        .review-card {
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .review-cover {
            height: 200px;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        .review-content {
            padding: 1rem;
        }
        .review-message {
            background-color: #f8f9fa;
            padding: 0.75rem;
            border-radius: 0.25rem;
            margin-top: 0.75rem;
        }
        .review-actions {
            margin-top: 1rem;
            display: flex;
            justify-content: space-between;
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
                            <a class="nav-link" href="contestants.php">
                                <i class="bi bi-people"></i> 选手管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="review.php">
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
                    <h1 class="h2">选手审核</h1>
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
                
                <?php if (empty($pendingContestants)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill me-2"></i> 目前没有待审核的选手
                </div>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($pendingContestants as $contestant): ?>
                    <div class="col-md-6">
                        <div class="review-card">
                            <?php if (!empty($contestant['cover_photo'])): ?>
                            <div class="review-cover" style="background-image: url('../uploads/<?php echo $contestant['cover_photo']; ?>');"></div>
                            <?php endif; ?>
                            
                            <div class="review-content">
                                <h5><?php echo $contestant['name']; ?> (<?php echo $contestant['number']; ?>)</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>学院:</strong> <?php echo $contestant['college']; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>班级:</strong> <?php echo $contestant['class']; ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <strong>学号:</strong> <?php echo $contestant['student_id']; ?>
                                </div>
                                
                                <div class="review-message">
                                    <strong>参赛宣言:</strong>
                                    <p class="mb-0"><?php echo nl2br($contestant['message']); ?></p>
                                </div>
                                
                                <div class="review-actions">
                                    <a href="../detail.php?id=<?php echo $contestant['id']; ?>" class="btn btn-outline-primary" target="_blank">
                                        <i class="bi bi-box-arrow-up-right"></i> 查看详情
                                    </a>
                                    
                                    <div>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="contestant_id" value="<?php echo $contestant['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success" onclick="return confirm('确定通过该选手的审核吗?');">
                                                <i class="bi bi-check-lg"></i> 通过
                                            </button>
                                        </form>
                                        
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="contestant_id" value="<?php echo $contestant['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('确定拒绝该选手吗?');">
                                                <i class="bi bi-x-lg"></i> 拒绝
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
