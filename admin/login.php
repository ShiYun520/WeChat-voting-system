<?php
require_once '../includes/db_config.php';
require_once '../includes/functions.php';

session_start();

// 如果已经登录，则重定向到管理首页
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error_message = '';

// 处理登录表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    if (empty($username) || empty($password)) {
        $error_message = '请输入用户名和密码';
    } else {
        try {
            $conn = getDbConnection();
            
            $stmt = $conn->prepare("SELECT id, username, password FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // 登录成功
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                
                // 记录登录时间
                $stmt = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // 重定向到管理首页
                header('Location: index.php');
                exit;
            } else {
                $error_message = '用户名或密码不正确';
            }
        } catch (PDOException $e) {
            $error_message = '系统错误，请稍后再试';
            // 实际生产环境可能需要记录日志
            // error_log('Login error: ' . $e->getMessage());
        }
    }
}

$pageTitle = "管理员登录 - 红文之光 照亮童年振兴梦";
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: "Microsoft YaHei", sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .login-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            background-color: #343a40;
            color: #fff;
            padding: 20px;
            text-align: center;
        }
        .login-body {
            padding: 30px;
        }
        .login-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #eee;
            padding: 15px;
            text-align: center;
            font-size: 0.9rem;
            color: #6c757d;
        }
        .login-logo {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .login-description {
            font-size: 14px;
            opacity: 0.8;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(52, 58, 64, 0.25);
            border-color: #343a40;
        }
        .btn-dark {
            background-color: #343a40;
            border-color: #343a40;
        }
        .btn-dark:hover, .btn-dark:focus {
            background-color: #23272b;
            border-color: #1d2124;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">管理控制台</div>
            <div class="login-description">红文之光 照亮童年振兴梦</div>
        </div>
        
        <div class="login-body">
            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            <form method="post" action="login.php">
                <div class="mb-3">
                    <label for="username" class="form-label">用户名</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required autofocus>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">密码</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-dark">
                        <i class="bi bi-box-arrow-in-right me-2"></i>登录
                    </button>
                </div>
            </form>
        </div>
        
        <div class="login-footer">
            &copy; 2023 红文之光 - 管理控制台
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
