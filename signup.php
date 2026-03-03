<?php
// 开启详细错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 连接数据库并处理表单提交
session_start();
// 引入数据库配置和函数
require_once 'includes/db_config.php';
require_once 'includes/functions.php';

// 获取网站标题和活动名称
try {
    $conn = getDbConnection();
    $site_title = "红文之光 照亮童年振兴梦";
    $contest_name = "红文之光 照亮童年振兴梦";
  
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('site_title', 'contest_name', 'end_time')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
  
    if (isset($settings['site_title']) && !empty($settings['site_title'])) {
        $site_title = $settings['site_title'];
    }
  
    if (isset($settings['contest_name']) && !empty($settings['contest_name'])) {
        $contest_name = $settings['contest_name'];
    }
  
    // 获取结束时间
    $end_time = "2023-12-31 23:59:59"; // 默认结束时间
    if (isset($settings['end_time']) && !empty($settings['end_time'])) {
        $end_time = $settings['end_time'];
    }
} catch (PDOException $e) {
    error_log("数据库连接或查询失败: " . $e->getMessage());
    $site_title = "红文之光 照亮童年振兴梦";
    $contest_name = "红文之光 照亮童年振兴梦";
    $end_time = "2023-12-31 23:59:59";
}

// 获取倒计时数据
function getCountdown() {
    global $end_time;
  
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

$countdown = getCountdown();

// 处理表单提交
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 记录表单提交事件
    error_log("接收到表单提交 - " . date('Y-m-d H:i:s'));
  
    // 验证必填字段
    $required_fields = ['name', 'phone', 'college', 'class', 'student_id'];
    $missing_fields = [];
  
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
  
    if (!empty($missing_fields)) {
        $errorMessage = "请填写所有必填字段: " . implode(', ', $missing_fields);
        error_log("表单验证失败: " . $errorMessage);
    } else {
        // 准备数据
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $college = trim($_POST['college']);
        $class = trim($_POST['class']);
        $student_id = trim($_POST['student_id']);
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
      
        error_log("表单数据: 姓名=$name, 电话=$phone, 学院=$college, 班级=$class, 学号=$student_id");
      
        // 处理封面上传
        $cover_photo = '';
        if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] == 0) {
            $upload_dir = 'uploads/covers/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
          
            $file_name = time() . '_' . basename($_FILES['cover_photo']['name']);
            $target_file = $upload_dir . $file_name;
          
            if (move_uploaded_file($_FILES['cover_photo']['tmp_name'], $target_file)) {
                $cover_photo = $target_file;
                error_log("封面上传成功: $cover_photo");
            } else {
                $errorMessage = "封面上传失败: " . $_FILES['cover_photo']['error'];
                error_log("封面上传失败: " . $_FILES['cover_photo']['error']);
            }
        } else {
            $errorMessage = "请上传封面照片 (错误码: " . (isset($_FILES['cover_photo']) ? $_FILES['cover_photo']['error'] : '未提交') . ")";
            error_log("封面上传缺失或有错误: " . (isset($_FILES['cover_photo']) ? $_FILES['cover_photo']['error'] : '未提交'));
        }
      
        // 处理活动照片上传（1-5张）
        $photos = [];
        if (isset($_FILES['photos'])) {
            $upload_dir = 'uploads/photos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
          
            $file_count = count($_FILES['photos']['name']);
            error_log("活动照片数量: $file_count");
          
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['photos']['error'][$i] == 0) {
                    $file_name = time() . '_' . basename($_FILES['photos']['name'][$i]);
                    $target_file = $upload_dir . $file_name;
                  
                    if (move_uploaded_file($_FILES['photos']['tmp_name'][$i], $target_file)) {
                        $photos[] = $target_file;
                        error_log("活动照片上传成功: $target_file");
                    } else {
                        error_log("活动照片上传失败: " . $_FILES['photos']['error'][$i]);
                    }
                }
            }
        }
      
        // 如果没有错误，将数据保存到数据库
        if (empty($errorMessage)) {
            try {
            } catch (Exception $e) {
    // 处理异常
    echo "发生错误: " . $e->getMessage();
}
                // 开始事务
                $conn->beginTransaction();
              
                // 生成选手编号
                $stmt = $conn->query("SELECT MAX(CAST(number AS UNSIGNED)) as max_number FROM contestants");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $number = isset($row['max_number']) ? $row['max_number'] + 1 : 1;
                error_log("生成选手编号: $number");
              
                // 从表单中获取class_name值（如果表单有此字段）
                $class_name = isset($_POST['class_name']) ? trim($_POST['class_name']) : '';
              
                // 如果class_name为空，可以根据$class生成一个默认值
                if (empty($class_name)) {
                    $class_name = "班级" . $class;
                }
              
                // 插入选手信息
                $stmt = $conn->prepare("
                    INSERT INTO contestants (number, name, phone, college, `class`, class_name, student_id, message, cover_photo, created_at, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')
                ");
              
                error_log("准备执行插入语句");
                $stmt->execute([
                    $number, 
                    $name, 
                    $phone, 
                    $college, 
                    $class, 
                    $class_name, 
                    $student_id, 
                    $message, 
                    $cover_photo
                ]);
              
                $contestant_id = $conn->lastInsertId();
                error_log("选手信息插入成功，ID: $contestant_id");
              
                // 插入活动照片
                if (!empty($photos)) {
                    $photo_stmt = $conn->prepare("INSERT INTO contestant_photos (contestant_id, photo_path, sort_order, created_at) VALUES (?, ?, ?, NOW())");
                  
                    $sort_order = 1;
                    foreach ($photos as $photo) {
                        $photo_stmt->execute([$contestant_id, $photo, $sort_order]);
                        $sort_order++;
                        error_log("照片插入成功: $photo");
                    }
                }
              
                $conn->commit();
$successMessage = "报名成功！您的编号为：" . $number . "。请等待审核。";
error_log("表单处理完成: $successMessage");
// 将成功消息存入SESSION，以便在重定向后显示
$_SESSION['success_message'] = $successMessage;
// 重定向到signup.html
header('Location: signup.html');
exit;

            }
        }
    }

// 在处理完表单后显示调试信息和消息
if (!empty($errorMessage)) {
    echo "<script>alert('错误：" . addslashes($errorMessage) . "');</script>";
}
if (!empty($successMessage)) {
    echo "<script>alert('成功：" . addslashes($successMessage) . "');</script>";
}
// 更详细的错误记录
if (!file_exists($db_config_path)) {
    error_log('尝试访问的数据库配置文件路径: ' . $db_config_path);
    error_log('当前脚本路径: ' . __FILE__);
    error_log('当前目录: ' . __DIR__);
    throw new Exception('数据库配置文件不存在');
}

?>
