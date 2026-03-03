<?php
require_once 'includes/db_config.php';

// 设置管理员信息
$admin_username = 'admin';
$admin_password = 'your_secure_password'; // 请使用强密码

// 连接数据库
$conn = getConnection();

// 检查表是否存在，不存在则创建
$conn->query("
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// 检查是否已存在管理员
$result = $conn->query("SELECT id FROM admin_users LIMIT 1");
if ($result->num_rows > 0) {
    echo "管理员账户已存在，不能重复创建！";
} else {
    // 创建管理员账户
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO admin_users (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $admin_username, $hashed_password);
    
    if ($stmt->execute()) {
        echo "管理员账户创建成功！<br>";
        echo "用户名: " . $admin_username . "<br>";
        echo "密码: " . $admin_password . "<br>";
        echo "请记住这些信息，并在使用后删除本设置脚本！";
    } else {
        echo "创建管理员账户失败: " . $stmt->error;
    }
    
    $stmt->close();
}

$conn->close();
?>
