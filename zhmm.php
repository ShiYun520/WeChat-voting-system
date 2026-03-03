<?php
// 数据库配置
$db_host = 'localhost';     // 数据库主机地址
$db_user = 'tp520';          // 数据库用户名
$db_pass = 'tp520';              // 数据库密码
$db_name = 'voting_system'; // 数据库名称

// 创建数据库连接
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 设置字符集
$conn->set_charset("utf8mb4");

// 管理员信息
$username = '614779780';
$password = '614779780';  // 实际使用中应该使用更复杂的密码

// 哈希密码
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// 检查表是否存在，不存在则创建
$conn->query("
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

if ($conn->error) {
    die("创建表失败: " . $conn->error);
}

// 插入管理员用户
$stmt = $conn->prepare("INSERT INTO admin_users (username, password) VALUES (?, ?)");
$stmt->bind_param("ss", $username, $hashed_password);

if ($stmt->execute()) {
    echo "管理员账户创建成功！<br>";
    echo "用户名: " . $username . "<br>";
    echo "密码: " . $password . "<br>";
    echo "请记住这些信息，并在使用后删除此文件！";
} else {
    echo "创建失败: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
