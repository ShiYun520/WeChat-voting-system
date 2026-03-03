<?php
// detail.php
require_once 'includes/db_config.php';
require_once 'includes/functions.php';

// 启动会话以支持投票功能
session_start();

// 获取选手ID并防止SQL注入
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    // 没有有效ID，重定向到首页
    header('Location: index.php');
    exit;
}

try {
    $conn = getDbConnection();

    // 查询选手信息
    $stmt = $conn->prepare("
        SELECT c.*, 
               COUNT(DISTINCT v.id) AS vote_count 
        FROM contestants c
        LEFT JOIN votes v ON c.id = v.contestant_id
        WHERE c.id = ? AND c.status = 'approved'
        GROUP BY c.id
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // 找不到选手或未审核通过，重定向到首页
        header('Location: index.php?error=contestant_not_found');
        exit;
    }

    // 增加浏览量
    $stmt = $conn->prepare("UPDATE contestants SET views = views + 1 WHERE id = ?");
    $stmt->execute([$id]);

    // 记录访问
    $ip = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $stmt = $conn->prepare("
        INSERT INTO page_views (page_type, reference_id, ip_address, user_agent) 
        VALUES ('detail', ?, ?, ?)
    ");
    $stmt->execute([$id, $ip, $userAgent]);

    // 获取选手照片
    $stmt = $conn->prepare("
        SELECT photo_path 
        FROM contestant_photos 
        WHERE contestant_id = ? 
        ORDER BY sort_order
    ");
    $stmt->execute([$id]);
    $photos = [];
    while ($photo = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $photos[] = $photo['photo_path'];
    }

    // 获取排名信息
    $rankStmt = $conn->prepare("
        SELECT COUNT(*) as rank
        FROM contestants c1
        JOIN (
            SELECT c2.id, COUNT(DISTINCT v.id) as votes
            FROM contestants c2
            LEFT JOIN votes v ON c2.id = v.contestant_id
            WHERE c2.status = 'approved'
            GROUP BY c2.id
        ) as vote_counts ON vote_counts.id = c1.id
        WHERE vote_counts.votes > (
            SELECT COUNT(DISTINCT v.id)
            FROM votes v
            WHERE v.contestant_id = ?
        )
        AND c1.status = 'approved'
    ");
    $rankStmt->execute([$id]);
    $rankData = $rankStmt->fetch(PDO::FETCH_ASSOC);
    $rank = $rankData['rank'] + 1; // 排名从1开始

    // 获取距离上一名还差多少票
    $gapStmt = $conn->prepare("
        SELECT MIN(vote_diff) as vote_gap
        FROM (
            SELECT (vote_counts.votes - (
                SELECT COUNT(DISTINCT v.id)
                FROM votes v
                WHERE v.contestant_id = ?
            )) as vote_diff
            FROM contestants c1
            JOIN (
                SELECT c2.id, COUNT(DISTINCT v.id) as votes
                FROM contestants c2
                LEFT JOIN votes v ON c2.id = v.contestant_id
                WHERE c2.status = 'approved'
                GROUP BY c2.id
            ) as vote_counts ON vote_counts.id = c1.id
            WHERE vote_counts.votes > (
                SELECT COUNT(DISTINCT v.id)
                FROM votes v
                WHERE v.contestant_id = ?
            )
            AND c1.status = 'approved'
        ) as gaps
    ");
    $gapStmt->execute([$id, $id]);
    $gapData = $gapStmt->fetch(PDO::FETCH_ASSOC);
    $voteGap = ($gapData['vote_gap'] !== null) ? $gapData['vote_gap'] : 0;

    // 获取结束时间
    $endTimeStmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'end_time'");
    $endTimeStmt->execute();
    $endTimeData = $endTimeStmt->fetch(PDO::FETCH_ASSOC);
    $endTime = isset($endTimeData['setting_value']) ? strtotime($endTimeData['setting_value']) : strtotime('2023-12-31 23:59:59');

    // 生成Meta标签用于社交分享
    $pageTitle = $row['name'] . " - " . $row['number'] . "号 - 红文之光 照亮童年振兴梦";
    $pageDescription = "为" . $row['name'] . "点赞支持！" . substr($row['message'], 0, 100) . "...";
    $pageImage = $row['cover_photo'];
  
    // 检查今日投票限制
    $canVote = true;
    $votesLeftToday = 0;
    $voteMessage = '';
  
    // 获取每日投票限制
    $voteLimitStmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'vote_limit_per_day'");
    $voteLimitStmt->execute();
    $voteLimitData = $voteLimitStmt->fetch(PDO::FETCH_ASSOC);
    $voteLimit = isset($voteLimitData['setting_value']) ? intval($voteLimitData['setting_value']) : 10;
  
    // 检查当前用户今日已投票次数
    $todayStart = date('Y-m-d 00:00:00');
    $todayEnd = date('Y-m-d 23:59:59');
    $voteCheckStmt = $conn->prepare("
        SELECT COUNT(*) as vote_count 
        FROM votes 
        WHERE ip_address = ? 
        AND created_at BETWEEN ? AND ?
    ");
    $voteCheckStmt->execute([$ip, $todayStart, $todayEnd]);
    $voteCheckData = $voteCheckStmt->fetch(PDO::FETCH_ASSOC);
    $todayVotes = $voteCheckData['vote_count'];
  
    $votesLeftToday = $voteLimit - $todayVotes;
  
    if ($votesLeftToday <= 0) {
        $canVote = false;
        $voteMessage = '您今日的投票次数已用完，明天再来吧！';
    }
  
    // 检查活动是否已结束
    if (time() > $endTime) {
        $canVote = false;
        $voteMessage = '活动已结束，不能再投票了！';
    }
  
    // 处理投票请求
    $voteSuccess = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vote']) && $canVote) {
        // 生成一个投票令牌以防止重复提交
        $voteToken = md5($ip . time() . rand(1000, 9999));
      
        // 记录投票
        $voteStmt = $conn->prepare("
            INSERT INTO votes (contestant_id, ip_address, user_agent, vote_token, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $result = $voteStmt->execute([$id, $ip, $userAgent, $voteToken]);
      
        if ($result) {
            $voteSuccess = true;
            // 更新投票计数
            $row['vote_count']++;
            // 减少剩余投票次数
            $votesLeftToday--;
          
            if ($votesLeftToday <= 0) {
                $canVote = false;
                $voteMessage = '您今日的投票次数已用完，明天再来吧！';
            }
        }
    }
} catch (PDOException $e) {
    $errorMessage = "数据库错误 [" . date('Y-m-d H:i:s') . "]: " . $e->getMessage() . 
                    " in file " . $e->getFile() . 
                    " on line " . $e->getLine();
  
    // 记录到网站根目录下的自定义日志文件
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/db_errors.log', $errorMessage . "\n\n", FILE_APPEND);
  
    // 重定向到错误页面
    header('Location: error.php?msg=database_error');
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="format-detection" content="telephone=no">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $pageDescription; ?>">
  
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>">
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="<?php echo $pageDescription; ?>">
    <?php if (!empty($pageImage)): ?>
    <meta property="og:image" content="<?php echo $pageImage; ?>">
    <?php endif; ?>
  
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>">
    <meta property="twitter:title" content="<?php echo $pageTitle; ?>">
    <meta property="twitter:description" content="<?php echo $pageDescription; ?>">
    <?php if (!empty($pageImage)): ?>
    <meta property="twitter:image" content="<?php echo $pageImage; ?>">
    <?php endif; ?>
    
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://unpkg.com/swiper@8/swiper-bundle.min.css">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        /* iOS风格基础样式 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Helvetica Neue", Helvetica, Arial, sans-serif;
        }
        
        body {
            background-color: #f6f6f6;
            color: #333;
            font-size: 14px;
            line-height: 1.5;
            padding-bottom: 70px;
        }
        
        /* iOS风格卡片 */
        .ios-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin: 12px;
            overflow: hidden;
            position: relative;
        }
        
        /* iOS风格标题 */
        .ios-title {
            font-weight: 600;
            font-size: 18px;
            color: #000;
            margin-bottom: 8px;
        }
        
        .ios-subtitle {
            font-weight: 400;
            font-size: 14px;
            color: #666;
            margin-bottom: 16px;
        }
        
        /* iOS风格按钮 */
        .ios-button {
            display: inline-block;
            background: #007AFF;
            color: white;
            border-radius: 12px;
            padding: 14px 0;
            width: 48%;
            text-align: center;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.2s;
            text-decoration: none;
            border: none;
            outline: none;
            cursor: pointer;
            -webkit-appearance: none;
        }
        
        .ios-button:active {
            transform: scale(0.98);
            background: #0061CC;
        }
        
        .ios-button.secondary {
            background: linear-gradient(45deg, #FF2D55, #FF9500);
        }
        
        .ios-button.secondary:active {
            background: linear-gradient(45deg, #D81B3B, #D67D00);
        }
        
        /* 顶部通知栏 */
        .top-notice {
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: white;
            padding: 8px 15px;
            font-size: 12px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            display: flex;
            align-items: center;
        }
        
        .top-notice img {
            width: 14px;
            height: 14px;
            margin-right: 8px;
        }
        
        /* 音乐播放按钮 */
        .music-btn {
            position: fixed;
            top: 40px;
            right: 15px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 99;
        }
        
        .music-animation {
            width: 24px;
            height: 24px;
            background-size: contain;
            animation: rotate 3s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* 头部图片 */
        .header-image {
            width: 100%;
            height: auto;
            display: block;
        }
        
        /* 个人资料卡 */
        .profile-card {
            padding: 16px;
            text-align: center;
        }
        
        .profile-card .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 12px;
            border: 3px solid white;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .profile-card .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-info {
            display: flex;
            justify-content: space-around;
            margin: 15px 0;
        }
        
        .profile-info .item {
            flex: 1;
            text-align: center;
        }
        
        .profile-info .number {
            font-size: 22px;
            font-weight: 600;
            color: #000;
        }
        
        .profile-info .label {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        /* 闪烁动画 */
        @keyframes shimmer {
            0% { color: #FF2D55; }
            33% { color: #5AC8FA; }
            66% { color: #FF9500; }
            100% { color: #FF2D55; }
        }
        
        .shimmer-text {
            font-size: 24px;
            font-weight: 700;
            animation: shimmer 3s linear infinite;
        }
        
        /* 倒计时样式 */
        .countdown {
            background: linear-gradient(135deg, #5AC8FA, #007AFF);
            color: white;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            margin: 15px 0;
        }
        
        .countdown-number {
            display: inline-block;
            background: rgba(255, 255, 255, 0.25);
            border-radius: 6px;
            padding: 5px 8px;
            margin: 0 2px;
            font-weight: 600;
            min-width: 30px;
        }
        
        /* 底部导航 */
        .tab-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 -1px 0 rgba(0, 0, 0, 0.1);
            display: flex;
            z-index: 90;
        }
        
        .tab-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #8E8E93;
            font-size: 10px;
        }
        
        .tab-item.active {
            color: #007AFF;
        }
        
        .tab-icon {
            font-size: 22px;
            margin-bottom: 4px;
        }
        
        /* 分享按钮 */
        .share-btn {
            position: fixed;
            right: 15px;
            bottom: 75px;
            width: 45px;
            height: 45px;
            border-radius: 22.5px;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 80;
        }
        
        /* 选手介绍 */
        .description {
            padding: 15px;
            color: #333;
            line-height: 1.6;
            font-size: 15px;
        }
        
        /* 图片展示 */
        .gallery {
            padding: 15px;
        }
        
        .gallery img {
            width: 100%;
            border-radius: 8px;
            margin-bottom: 12px;
        }
        
        /* 免责声明 */
        .disclaimer {
            margin: 20px 0;
            text-align: center;
        }
        
        .disclaimer a {
            display: inline-block;
            padding: 8px 16px;
            background: rgba(142, 142, 147, 0.12);
            color: #8E8E93;
            border-radius: 15px;
            text-decoration: none;
            font-size: 12px;
        }
        
        /* 回到顶部按钮 */
        .back-top {
            position: fixed;
            right: 15px;
            bottom: 130px;
            width: 45px;
            height: 45px;
            border-radius: 22.5px;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 80;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .back-top.visible {
            opacity: 1;
        }
            /* 添加超链接样式 */
    .tab-item a {
        text-decoration: none;
        color: inherit;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
    }
 /* 轮播图样式 */
.ios-card .swiper-container {
    width: 100%;
    padding: 10px 15px 20px;
}

.ios-card .swiper-slide {
    display: flex;
    justify-content: center;
    align-items: center;
    background: #f8f8f8;
    border-radius: 10px;
    overflow: hidden;
    height: auto; /* 允许高度自适应 */
}

.ios-card .swiper-slide img {
    max-width: 100%; /* 确保图片宽度不超过容器 */
    max-height: 70vh; /* 图片最大高度为视窗高度的70% */
    width: auto; /* 宽度自适应 */
    height: auto; /* 高度自适应 */
    object-fit: contain; /* 确保整个图片可见且保持原比例 */
    border-radius: 8px;
}

/* 分页器样式 */
.ios-card .swiper-pagination {
    position: relative;
    margin-top: 10px;
}

.ios-card .swiper-pagination-bullet {
    width: 8px;
    height: 8px;
    background: #ccc;
    opacity: 1;
}

.ios-card .swiper-pagination-bullet-active {
    background: #007aff;
}

    </style>
</head>
<body>
    
    <header>
        <div class="m_head clearfix">
            <a href="http://fz.torgw.cc/index.php">
                <img src="http://longhua868.fss-my.addlink.cn//17423850687160410.jpg" alt="北华大学文学院" style="width:100%; height:auto">
            </a>
            <br>
    <!-- 音频元素 -->
    <audio id="bgMusic" src="assets/audio/background.mp3" loop></audio>
    
    <!-- 顶部通知栏 -->
    <div class="top-notice">
        <div>禁止刷赞等系列违规行为！
        </div>
    </div>
    
    <!-- 音乐按钮 -->
    <div class="music-btn" id="musicBtn">
        <div class="music-animation">
            <i class="fas fa-music"></i>
        </div>
    </div>
    
    <!-- 选手照片轮播 -->

    <!-- 个人资料卡 -->
    <div class="ios-card profile-card">
        <div class="avatar">
            <img src="<?php echo htmlspecialchars($row['cover_photo']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
        </div>
        <h1 class="ios-title"><?php echo htmlspecialchars($row['name']); ?> <small>(<?php echo htmlspecialchars($row['number']); ?>号)</small></h1>
        <p class="ios-subtitle"><?php echo htmlspecialchars($row['category']); ?></p>
        
        <div class="profile-info">
            <div class="item">
                <div class="number"><?php echo number_format($row['vote_count']); ?></div>
                <div class="label">总票数</div>
            </div>
            <div class="item">
                <div class="number"><?php echo number_format($rank); ?></div>
                <div class="label">当前排名</div>
            </div>
            <div class="item">
                <div class="number"><?php echo number_format($row['views']); ?></div>
                <div class="label">总浏览量</div>
            </div>
        </div>
        
        <?php if ($voteGap > 0): ?>
        <p class="shimmer-text">
            距离上一名还差 <?php echo number_format($voteGap); ?> 票!
        </p>
        <?php endif; ?>
        
        <!-- 投票提示 -->
        <?php if ($voteSuccess): ?>
        <div class="vote-alert vote-success">
            <i class="fas fa-check-circle"></i> 投票成功！感谢您的支持！
        </div>
        <?php elseif (!empty($voteMessage)): ?>
        <div class="vote-alert vote-warning">
            <i class="fas fa-exclamation-circle"></i> <?php echo $voteMessage; ?>
        </div>
        <?php endif; ?>
        
        <!-- 倒计时 -->
        <div class="countdown">
            <div style="margin-bottom: 8px;">距活动结束还剩:</div>
            <span id="days"></span>天
            <span id="hours"></span>时
            <span id="minutes"></span>分
            <span id="seconds"></span>秒
        </div>
        
<div style="display: flex; justify-content: space-between; margin-top: 20px;">
    <button class="ios-button" id="voteBtn">免费为TA点赞</button>
    <button class="ios-button secondary" id="helpBtn">自愿助力点赞</button>
</div>


<div class="rules-container" style="margin-top: 15px; text-align: center;">
    <button class="rules-button" id="showRulesBtn" style="background: none; border: none; color: #007AFF; font-size: 14px; text-decoration: underline; cursor: pointer;">
        点赞规则
    </button>
    <div id="rulesContent" style="display: none; margin-top: 10px; background: #f5f5f5; padding: 15px; border-radius: 8px; text-align: left; font-size: 14px;">
        <h4 style="margin-top: 0; color: #333;">点赞规则说明：</h4>
        <ul style="padding-left: 20px; margin-bottom: 0;">
            <li>每位用户每天可免费点赞1次</li>
            <li>点赞成功后不可取消</li>
            <li>自愿助力可增加更多点赞次数</li>
            <li>活动最终解释权归平台所有</li>
        </ul>
    </div>
</div>
</div>


<script>
    document.getElementById('showRulesBtn').addEventListener('click', function() {
        var rulesContent = document.getElementById('rulesContent');
        if (rulesContent.style.display === 'none') {
            rulesContent.style.display = 'block';
        } else {
            rulesContent.style.display = 'none';
        }
    });
</script>



    
    <!-- 选手介绍 -->
    <div class="ios-card">
        <h2 class="ios-title" style="padding: 15px 15px 0 15px;">选手介绍</h2>
        <div class="wave-divider">
            <div class="wave"></div>
        </div>
        <div class="description">
            <?php echo nl2br(htmlspecialchars($row['message'])); ?>
        </div>
    </div>
    
    <!-- 图片展示 -->
<!-- 选手照片展示 -->
<div class="ios-card">
    <h2 class="ios-title" style="padding: 15px 15px 0 15px;">更多照片</h2>
    <div class="wave-divider">
        <div class="wave"></div>
    </div>
    
    <!-- 轮播组件 -->
    <div class="swiper-container">
        <div class="swiper-wrapper">
            <?php foreach ($photos as $photo): ?>
            <div class="swiper-slide">
                <img src="<?php echo htmlspecialchars($photo); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>的照片">
            </div>
            <?php endforeach; ?>
        </div>
        <div class="swiper-pagination"></div>
    </div>
</div>  

        <div class="gallery">
            <?php foreach ($photos as $index => $photo): ?>
            <?php if ($index > 0): // 跳过第一张，因为已经在轮播中显示 ?>
            <img src="<?php echo htmlspecialchars($photo); ?>" alt="照片">
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- 免责声明 -->
    <div class="disclaimer">
        <a href="terms.php">活动规则 & 隐私声明</a>
    </div>
    
    <!-- 分享按钮 -->
    <div class="share-btn" id="shareBtn">
        <i class="fas fa-share-alt"></i>
    </div>
    
    <!-- 回到顶部按钮 -->
    <div class="back-top" id="backTop">
        <i class="fas fa-arrow-up"></i>
    </div>
    
    <!-- 底部导航 -->
    <div class="tab-bar">
        <div class="tab-item active">
            <a href="index.php">
                <div class="tab-icon">🏠</div>
                <div>首页</div>
            </a>
        </div>
        <div class="tab-item">
            <a href="top.php">
                <div class="tab-icon">🏆</div>
                <div>排行榜</div>
            </a>
        </div>
        <div class="tab-item">
            <a href="#">
                <div class="tab-icon">👍</div>
                <div>点赞</div>
            </a>
        </div>
        <div class="tab-item">
            <a href="signup.html">
                <div class="tab-icon">📝</div>
                <div>报名</div>
            </a>
        </div>
        <div class="tab-item">
            <a href="prize.html">
                <div class="tab-icon">🎁</div>
                <div>奖品</div>
            </a>
        </div>
    
    <!-- Swiper JS -->
    <script src="https://unpkg.com/swiper@8/swiper-bundle.min.js"></script>
    
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // 初始化Swiper
            var swiper = new Swiper('.swiper-container', {
                loop: true,
                autoplay: {
                    delay: 3000,
                    disableOnInteraction: false,
                },
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                }
            });
            
            // 背景音乐控制
            const musicBtn = document.getElementById('musicBtn');
            const bgMusic = document.getElementById('bgMusic');
            
            // 检查用户偏好
            const musicEnabled = localStorage.getItem('musicEnabled') === 'true';
            
            if (musicEnabled) {
                // 由于iOS的自动播放限制，我们设置一个音量为0的初始播放
                bgMusic.volume = 0;
                const playPromise = bgMusic.play();
                
                if (playPromise !== undefined) {
                    playPromise.then(_ => {
                        // 成功自动播放后，恢复音量
                        bgMusic.volume = 1;
                        musicBtn.classList.add('playing');
                    }).catch(error => {
                        // 自动播放被阻止
                        console.log("自动播放被阻止:", error);
                        localStorage.setItem('musicEnabled', 'false');
                    });
                }
            }
            
            musicBtn.addEventListener('click', function() {
                if (bgMusic.paused) {
                    bgMusic.play();
                    musicBtn.classList.add('playing');
                    localStorage.setItem('musicEnabled', 'true');
                } else {
                    bgMusic.pause();
                    musicBtn.classList.remove('playing');
                    localStorage.setItem('musicEnabled', 'false');
                }
            });
            
            // 倒计时功能
            function updateCountdown() {
                const endTime = <?php echo $endTime; ?> * 1000; // 转换为毫秒
                const now = new Date().getTime();
                const distance = endTime - now;
              
                if (distance < 0) {
                    document.getElementById("days").innerHTML = "0";
                    document.getElementById("hours").innerHTML = "00";
                    document.getElementById("minutes").innerHTML = "00";
                    document.getElementById("seconds").innerHTML = "00";
                    return;
                }
              
                // 计算天、时、分、秒
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
              
                // 更新显示
                document.getElementById("days").innerHTML = days;
                document.getElementById("hours").innerHTML = hours.toString().padStart(2, '0');
                document.getElementById("minutes").innerHTML = minutes.toString().padStart(2, '0');
                document.getElementById("seconds").innerHTML = seconds.toString().padStart(2, '0');
            }
            
            // 立即更新倒计时
            updateCountdown();
            
            // 每秒更新倒计时
            setInterval(updateCountdown, 1000);
            
            // 助力按钮点击事件
            document.getElementById('helpBtn').addEventListener('click', function() {
                alert('感谢您的助力支持！');
                // 这里可以添加助力相关的逻辑或跳转
            });
            
            // 分享按钮逻辑
            document.getElementById('shareBtn').addEventListener('click', function() {
                if (navigator.share) {
                    navigator.share({
                        title: '<?php echo $pageTitle; ?>',
                        text: '<?php echo $pageDescription; ?>',
                        url: window.location.href,
                    })
                    .catch((error) => console.log('分享失败:', error));
                } else {
                    // 如果浏览器不支持网页分享API，显示简单提示
                    alert('请截图或复制链接分享给好友！\n' + window.location.href);
                }
            });
            
            // 回到顶部按钮
            var backTopBtn = document.getElementById('backTop');
            
            window.addEventListener('scroll', function() {
                if (window.scrollY > 300) {
                    backTopBtn.classList.add('visible');
                } else {
                    backTopBtn.classList.remove('visible');
                }
            });
            
            backTopBtn.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            // 初始判断滚动位置
            if (window.scrollY > 300) {
                backTopBtn.classList.add('visible');
            }
        });
    </script>
</body>
<!-- ... existing code ... -->

<!-- ... existing code ... -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 获取点赞按钮
    const voteBtn = document.getElementById('voteBtn');
    
    // 添加点击事件监听器
    voteBtn.addEventListener('click', function() {
        // 获取选手ID - 从URL参数中获取
        const urlParams = new URLSearchParams(window.location.search);
        const contestantId = urlParams.get('id'); // 假设URL中的参数是 ?id=123
        
        if (!contestantId) {
            alert('无法获取选手ID，请刷新页面重试');
            return;
        }
        
        // 禁用按钮，防止重复点击
        voteBtn.disabled = true;
        voteBtn.textContent = '点赞成功';
        
        // 创建FormData对象用于发送数据
        const formData = new FormData();
        formData.append('contestant_id', contestantId);
        
        // 修正路径 - 使用正确的URL路径
        const voteUrl = 'vote_ajax.php'; // 改为相对路径或确认正确的绝对路径
        
// 在前端添加此代码，可以更好地捕获和显示错误
fetch(voteUrl, {
    method: 'POST',
    body: formData,
    headers: {
        'X-Requested-With': 'XMLHttpRequest'
    }
})
.then(response => {
    if (!response.ok) {
        return response.text().then(text => {
            try {
                // 尝试解析JSON
                const errorData = JSON.parse(text);
                throw new Error(errorData.message || '服务器错误: ' + response.status);
            } catch (e) {
                // 如果不是JSON，返回原始响应
                console.log('服务器返回的非JSON数据:', text);
                throw new Error('服务器返回错误 (' + response.status + ')，请联系管理员');
            }
        });
    }
    return response.json();
})
.then(data => {
    // 处理成功情况 (同上)
})
.catch(error => {
    console.error('点赞请求出错:', error);
    voteBtn.textContent = '免费为TA点赞';
    voteBtn.disabled = false;
    alert('点赞失败: ' + error.message);
});

    });
});


</script>
<!-- ... existing code ... -->

</body>
</html>

</html>
