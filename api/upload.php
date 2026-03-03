<?php
require_once '../includes/db_config.php';
require_once '../includes/functions.php';

// 设置响应类型为JSON
header('Content-Type: application/json');

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '仅支持POST请求']);
    exit;
}

// 检查活动是否开放报名
$conn = getConnection();
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'registration_open'");
$stmt->execute();
$stmt->bind_result($registrationOpen);
$stmt->fetch();
$stmt->close();

if ($registrationOpen !== 'true') {
    echo json_encode(['success' => false, 'message' => '报名已关闭']);
    exit;
}

// 处理表单数据
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$college = isset($_POST['college']) ? trim($_POST['college']) : '';
$class = isset($_POST['class']) ? trim($_POST['class']) : '';
$student_id = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// 验证必填字段
if (empty($name) || empty($phone) || empty($college) || empty($class) || empty($student_id)) {
    echo json_encode(['success' => false, 'message' => '请填写所有必填字段']);
    exit;
}

// 验证手机号格式
if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => '请输入有效的手机号码']);
    exit;
}

// 检查学号是否已经存在
$stmt = $conn->prepare("SELECT id FROM contestants WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => '该学号已报名，请勿重复提交']);
    exit;
}
$stmt->close();

// 处理文件上传
function handleFileUpload($fileInputName, $uploadDir) {
    // 确保目录存在
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // 检查文件是否存在
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] != UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => '文件上传失败'];
    }
    
    // 获取文件信息
    $file = $_FILES[$fileInputName];
    $fileName = $file['name'];
    $fileType = $file['type'];
    $fileTmpName = $file['tmp_name'];
    $fileError = $file['error'];
    $fileSize = $file['size'];
    
    // 检查文件类型
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => '只允许上传JPG、PNG或GIF图片'];
    }
    
    // 检查文件大小 (5MB)
    if ($fileSize > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => '文件大小不能超过5MB'];
    }
    
    // 生成安全的文件名
    $newFileName = generateSafeFileName($fileName);
    $destination = $uploadDir . '/' . $newFileName;
    
    // 移动上传的文件
    if (move_uploaded_file($fileTmpName, $destination)) {
        return ['success' => true, 'filePath' => $destination];
    } else {
        return ['success' => false, 'message' => '文件移动失败'];
    }
}

// 上传封面照片
$coverUpload = handleFileUpload('cover_photo', '../uploads/covers');
if (!$coverUpload['success']) {
    echo json_encode($coverUpload);
    exit;
}

// 生成选手编号
$year = date('Y');
$stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(number, 5) AS UNSIGNED)) FROM contestants WHERE number LIKE ?");
$numPattern = $year . '%';
$stmt->bind_param("s", $numPattern);
$stmt->execute();
$stmt->bind_result($maxNum);
$stmt->fetch();
$stmt->close();

$maxNum = $maxNum ? $maxNum : 0;
$nextNum = $maxNum + 1;
$number = $year . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

// 开始事务
$conn->begin_transaction();

try {
    // 插入选手基本信息
    $stmt = $conn->prepare("
        INSERT INTO contestants (number, name, phone, college, class_name, student_id, message, cover_photo, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $stmt->bind_param("ssssssss", 
        $number, 
        $name, 
        $phone, 
        $college, 
        $class, 
        $student_id, 
        $message, 
        $coverUpload['filePath']
    );
    
    $stmt->execute();
    $contestantId = $conn->insert_id;
    $stmt->close();
    
    // 处理额外的照片上传（最多5张）
    if (isset($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
        $photoCount = count($_FILES['photos']['name']);
        $validPhotos = 0;
        
        for ($i = 0; $i < $photoCount && $validPhotos < 5; $i++) {
            // 构建单个文件数组
            $photoFile = [
                'name' => $_FILES['photos']['name'][$i],
                'type' => $_FILES['photos']['type'][$i],
                'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                'error' => $_FILES['photos']['error'][$i],
                'size' => $_FILES['photos']['size'][$i]
            ];
            
            // 如果这个文件没有问题
            if ($photoFile['error'] === UPLOAD_ERR_OK) {
                // 临时保存到全局变量以便使用handleFileUpload函数
                $_FILES['temp_photo'] = $photoFile;
                
                $photoUpload = handleFileUpload('temp_photo', '../uploads/photos');
                if ($photoUpload['success']) {
                    // 插入照片记录
                    $stmt = $conn->prepare("
                        INSERT INTO contestant_photos (contestant_id, photo_path, sort_order) 
                        VALUES (?, ?, ?)
                    ");
                    
                    $sortOrder = $validPhotos;
                    $stmt->bind_param("isi", $contestantId, $photoUpload['filePath'], $sortOrder);
                    $stmt->execute();
                    $stmt->close();
                    
                    $validPhotos++;
                }
            }
        }
    }
    
    // 提交事务
    $conn->commit();
    
    // 返回成功结果
    echo json_encode([
        'success' => true,
        'message' => '报名成功！您的选手编号是：' . $number,
        'contestant_id' => $contestantId,
        'number' => $number
    ]);
    
} catch (Exception $e) {
    // 回滚事务
    $conn->rollback();
    
    echo json_encode([
        'success' => false, 
        'message' => '报名失败，请稍后再试: ' . $e->getMessage()
    ]);
}

// 关闭数据库连接
$conn->close();
?>
