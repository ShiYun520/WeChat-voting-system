<?php
require_once '../includes/db_config.php';
require_once '../includes/functions.php';

// 验证管理员登录
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 获取统计数据
$conn = getDbConnection();

// 获取选手统计
$stmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM contestants) AS total_contestants,
        (SELECT COUNT(*) FROM contestants WHERE status = 'pending') AS pending_contestants,
        (SELECT COUNT(*) FROM contestants WHERE status = 'approved') AS approved_contestants,
        (SELECT COUNT(*) FROM contestants WHERE status = 'rejected') AS rejected_contestants
");
$stmt->execute();
$contestantStats = $stmt->fetch(PDO::FETCH_ASSOC);

// 获取投票统计
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total_votes,
           (SELECT COUNT(*) FROM votes WHERE DATE(created_at) = CURDATE()) AS today_votes
    FROM votes
");
$stmt->execute();
$voteStats = $stmt->fetch(PDO::FETCH_ASSOC);

// 获取最新注册的选手
$stmt = $conn->prepare("
    SELECT id, number, name, college, status, created_at
    FROM contestants
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$recentContestants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取最新投票记录
$stmt = $conn->prepare("
    SELECT v.id, v.created_at, v.ip_address, c.id AS contestant_id, c.name AS contestant_name, c.number
    FROM votes v
    JOIN contestants c ON v.contestant_id = c.id
    ORDER BY v.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recentVotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// PDO 不需要显式关闭连接
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - 红文之光 照亮童年振兴梦</title>
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
        .dashboard-card {
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: transform 0.3s;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .stats-icon {
            font-size: 2rem;
            opacity: 0.8;
        }
        .status-pending {
            color: #ffc107;
        }
        .status-approved {
            color: #198754;
        }
        .status-rejected {
            color: #dc3545;
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
                            <a class="nav-link active" href="index.php">
                                <i class="bi bi-house"></i> 仪表盘
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="contestants.php">
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
                    <h1 class="h2">管理仪表盘</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="../index.php" target="_blank" class="btn btn-sm btn-outline-secondary">前台首页</a>
                        </div>
                    </div>
                </div>

                <!-- 统计卡片 -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="dashboard-card card border-left-primary h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">总选手数</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $contestantStats['total_contestants']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-people-fill stats-icon text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="dashboard-card card border-left-success h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">待审核选手</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $contestantStats['pending_contestants']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-hourglass-split stats-icon text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="dashboard-card card border-left-info h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">总投票数</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $voteStats['total_votes']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-hand-thumbs-up-fill stats-icon text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="dashboard-card card border-left-warning h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">今日投票</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $voteStats['today_votes']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-calendar-check-fill stats-icon text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- 最近选手 -->
                    <div class="col-lg-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-list-ul me-1"></i>
                                最新注册选手
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>编号</th>
                                                <th>姓名</th>
                                                <th>学院</th>
                                                <th>状态</th>
                                                <th>注册时间</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentContestants as $contestant): ?>
                                            <tr>
                                                <td><?php echo $contestant['number']; ?></td>
                                                <td><?php echo $contestant['name']; ?></td>
                                                <td><?php echo $contestant['college']; ?></td>
                                                <td>
                                                    <?php if ($contestant['status'] == 'pending'): ?>
                                                        <span class="badge bg-warning">待审核</span>
                                                    <?php elseif ($contestant['status'] == 'approved'): ?>
                                                        <span class="badge bg-success">已通过</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">已拒绝</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($contestant['created_at'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 最近投票 -->
                    <div class="col-lg-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-hand-thumbs-up me-1"></i>
                                最新投票记录
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>选手</th>
                                                <th>编号</th>
                                                <th>IP地址</th>
                                                <th>投票时间</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentVotes as $vote): ?>
                                            <tr>
                                                <td><?php echo $vote['contestant_name']; ?></td>
                                                <td><?php echo $vote['number']; ?></td>
                                                <td><?php echo $vote['ip_address']; ?></td>
                                                <td><?php echo date('Y-m-d H:i:s', strtotime($vote['created_at'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
