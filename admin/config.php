<?php
require_once '../includes/db_config.php';
require_once '../includes/functions.php';

// 验证管理员登录
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 处理设置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        $registration_open = isset($_POST['registration_open']) ? 'true' : 'false';
        $vote_limit_per_day = isset($_POST['vote_limit_per_day']) ? intval($_POST['vote_limit_per_day']) : 10;
        $contest_start = isset($_POST['contest_start']) ? trim($_POST['contest_start']) : '';
        $contest_end = isset($_POST['contest_end']) ? trim($_POST['contest_end']) : '';
        $site_title = isset($_POST['site_title']) ? trim($_POST['site_title']) : '';
        $site_description = isset($_POST['site_description']) ? trim($_POST['site_description']) : '';
        
        try {
            $conn = getDbConnection();
            
            // 开始事务
            $conn->beginTransaction();
            
            // 更新设置
            $settings = [
                'registration_open' => $registration_open,
                'vote_limit_per_day' => $vote_limit_per_day,
                'contest_start' => $contest_start,
                'contest_end' => $contest_end,
                'site_title' => $site_title,
                'site_description' => $site_description
            ];
            
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            
            foreach ($settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            
            // 提交事务
            $conn->commit();
            
            $_SESSION['message'] = '系统设置已更新';
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            // 回滚事务
            if (isset($conn)) {
                $conn->rollBack();
            }
            
            $_SESSION['message'] = '更新系统设置失败: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }
    } elseif (isset($_POST['update_admin_password'])) {
        // 更新管理员密码
        $current_password = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
        $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
        $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
        
        // 验证新密码
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['message'] = '所有密码字段都必须填写';
            $_SESSION['message_type'] = 'danger';
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['message'] = '新密码与确认密码不匹配';
            $_SESSION['message_type'] = 'danger';
        } elseif (strlen($new_password) < 6) {
            $_SESSION['message'] = '新密码长度至少为6个字符';
            $_SESSION['message_type'] = 'danger';
        } else {
            try {
                $conn = getDbConnection();
                
                // 验证当前密码
                $admin_username = $_SESSION['admin_username'];
                $stmt = $conn->prepare("SELECT password FROM admin_users WHERE username = ?");
                $stmt->execute([$admin_username]);
                $stored_password = $stmt->fetchColumn();
                
                if (password_verify($current_password, $stored_password)) {
                    // 更新密码
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE admin_users SET password = ? WHERE username = ?");
                    $stmt->execute([$new_password_hash, $admin_username]);
                    
                    $_SESSION['message'] = '管理员密码已更新';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = '当前密码不正确';
                    $_SESSION['message_type'] = 'danger';
                }
            } catch (PDOException $e) {
                $_SESSION['message'] = '更新密码失败: ' . $e->getMessage();
                $_SESSION['message_type'] = 'danger';
            }
        }
    }
    
    // 重定向以防止表单重复提交
    header('Location: config.php');
    exit;
}

// 获取当前系统设置
$settings = [
    'registration_open' => 'true',
    'vote_limit_per_day' => '10',
    'contest_start' => date('Y-m-d H:i:s'),
    'contest_end' => date('Y-m-d H:i:s', strtotime('+30 days')),
    'site_title' => '红文之光 照亮童年振兴梦',
    'site_description' => '富体月榜挑战赛内公益主题活动'
];

try {
    $conn = getDbConnection();
    $stmt = $conn->query("SELECT setting_key, setting_value FROM settings");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // 可以记录错误，但继续使用默认设置
    // error_log('获取设置失败: ' . $e->getMessage());
}

$pageTitle = "系统设置 - 红文之光 照亮童年振兴梦";
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: "Microsoft YaHei", sans-serif;
        }
        .admin-sidebar {
            background-color: #343a40;
            min-height: 100vh;
            color: #fff;
        }
        .admin-content {
            padding: 20px;
        }
        .sidebar-link {
            color: #adb5bd;
            text-decoration: none;
            padding: 8px 16px;
            display: block;
            transition: all 0.3s;
        }
        .sidebar-link:hover, .sidebar-link.active {
            color: #fff;
            background-color: rgba(255,255,255,0.1);
        }
        .page-title {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        .settings-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            padding: 20px;
        }
        .settings-title {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
            color: #495057;
        }
        .form-switch {
            padding-left: 2.5em;
        }
        .form-switch .form-check-input {
            width: 3em;
            height: 1.5em;
            margin-left: -2.5em;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- 侧边栏 -->
            <div class="col-md-2 admin-sidebar p-0">
                <div class="p-3 text-center">
                    <h5>管理控制台</h5>
                </div>
                <div class="mt-3">
                    <a href="index.php" class="sidebar-link">
                        <i class="bi bi-speedometer2 me-2"></i> 仪表盘
                    </a>
                    <a href="contestants.php" class="sidebar-link">
                        <i class="bi bi-people me-2"></i> 选手管理
                    </a>
                    <a href="review.php" class="sidebar-link">
                        <i class="bi bi-check-square me-2"></i> 审核选手
                    </a>
                    <a href="config.php" class="sidebar-link active">
                        <i class="bi bi-gear me-2"></i> 系统设置
                    </a>
                    <a href="logout.php" class="sidebar-link mt-5">
                        <i class="bi bi-box-arrow-right me-2"></i> 退出登录
                    </a>
                </div>
            </div>
            
            <!-- 主内容区 -->
            <div class="col-md-10 admin-content">
                <h2 class="page-title">系统设置</h2>
                
                <!-- 提示消息 -->
                <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php 
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                endif; 
                ?>
                
                <!-- 系统设置表单 -->
                <div class="settings-card">
                    <h5 class="settings-title"><i class="bi bi-sliders me-2"></i>基本设置</h5>
                    
                    <form method="post" action="config.php">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="registration_open" name="registration_open" <?php echo $settings['registration_open'] === 'true' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="registration_open">开放报名</label>
                                </div>
                                <div class="mb-3">
                                    <label for="vote_limit_per_day" class="form-label">每日投票限制</label>
                                    <input type="number" class="form-control" id="vote_limit_per_day" name="vote_limit_per_day" value="<?php echo $settings['vote_limit_per_day']; ?>" min="1" max="100">
                                    <div class="form-text">每个IP每天最多可投票次数</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contest_start" class="form-label">活动开始时间</label>
                                    <input type="datetime-local" class="form-control" id="contest_start" name="contest_start" value="<?php echo date('Y-m-d\TH:i', strtotime($settings['contest_start'])); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="contest_end" class="form-label">活动结束时间</label>
                                    <input type="datetime-local" class="form-control" id="contest_end" name="contest_end" value="<?php echo date('Y-m-d\TH:i', strtotime($settings['contest_end'])); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="site_title" class="form-label">网站标题</label>
                            <input type="text" class="form-control" id="site_title" name="site_title" value="<?php echo htmlspecialchars($settings['site_title']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="site_description" class="form-label">网站描述</label>
                            <textarea class="form-control" id="site_description" name="site_description" rows="3"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                        </div>
                        
                        <input type="hidden" name="update_settings" value="1">
                        <button type="submit" class="btn btn-primary">保存设置</button>
                    </form>
                </div>
                
                <!-- 密码修改表单 -->
                <div class="settings-card">
                    <h5 class="settings-title"><i class="bi bi-shield-lock me-2"></i>安全设置</h5>
                    
                    <form method="post" action="config.php" class="row g-3">
                        <div class="col-md-4">
                            <label for="current_password" class="form-label">当前密码</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="new_password" class="form-label">新密码</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                        </div>
                        
                        <div class="col-md-4">
                            <label for="confirm_password" class="form-label">确认新密码</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                        </div>
                        
                        <div class="col-12">
                            <input type="hidden" name="update_admin_password" value="1">
                            <button type="submit" class="btn btn-warning">更改密码</button>
                        </div>
                    </form>
                </div>
                
                <!-- 系统信息 -->
                <div class="settings-card">
                    <h5 class="settings-title"><i class="bi bi-info-circle me-2"></i>系统信息</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th style="width: 200px;">PHP版本</th>
                                    <td><?php echo phpversion(); ?></td>
                                </tr>
                                <tr>
                                    <th>服务器软件</th>
                                    <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                                </tr>
                                <tr>
                                    <th>服务器时间</th>
                                    <td><?php echo date('Y-m-d H:i:s'); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th style="width: 200px;">MySQL版本</th>
                                    <td>
                                        <?php 
                                        try {
                                            $conn = getDbConnection();
                                            $version = $conn->query("SELECT VERSION() as version")->fetchColumn();
                                            echo $version;
                                        } catch (PDOException $e) {
                                            echo "无法获取";
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>文件上传最大大小</th>
                                    <td><?php echo ini_get('upload_max_filesize'); ?></td>
                                </tr>
                                <tr>
                                    <th>会话超时时间</th>
                                    <td><?php echo ini_get('session.gc_maxlifetime') . ' 秒'; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- 数据库备份 -->
                <div class="settings-card">
                    <h5 class="settings-title"><i class="bi bi-database me-2"></i>数据维护</h5>
                    
                    <div class="d-flex gap-3">
                        <a href="backup.php" class="btn btn-outline-primary">
                            <i class="bi bi-download"></i> 备份数据库
                        </a>
                        
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#clearDataModal">
                            <i class="bi bi-trash"></i> 清空投票数据
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 清空数据确认模态框 -->
    <div class="modal fade" id="clearDataModal" tabindex="-1" aria-labelledby="clearDataModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="clearDataModalLabel">危险操作确认</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        此操作将清空所有投票数据，并将所有选手的票数重置为0。此操作不可恢复!
                    </div>
                    <p>请输入 <strong>CONFIRM</strong> 以确认操作:</p>
                    <input type="text" id="confirmText" class="form-control">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" id="confirmClearBtn" class="btn btn-danger" disabled>确认清空数据</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 确认框验证
        document.getElementById('confirmText').addEventListener('input', function() {
            const confirmBtn = document.getElementById('confirmClearBtn');
            confirmBtn.disabled = this.value !== 'CONFIRM';
        });
        
        // 清空数据操作
        document.getElementById('confirmClearBtn').addEventListener('click', function() {
            // 这里添加AJAX请求来执行清空操作
            fetch('clear_votes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'clear_votes',
                    confirm: true
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('操作失败: ' + data.message);
                }
            })
            .catch(error => {
                alert('请求出错: ' + error);
            });
        });
    </script>
</body>
</html>
