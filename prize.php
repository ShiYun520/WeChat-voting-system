<?php
// 数据库连接
$servername = "localhost";
$username = "tp520";
$password = "tp520";
$dbname = "voting_system";

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// 获取网站标题 - 确保SQL语句不为空
$site_title = "";
$sql_site_title = "SELECT setting_value FROM settings WHERE setting_key = 'site_title'";
if ($sql_site_title) {
    $result_title = $conn->query($sql_site_title);
    if ($result_title && $result_title->num_rows > 0) {
        $site_title = $result_title->fetch_assoc()['setting_value'];
    }
}

// 获取活动结束时间
$end_time_str = "2023-12-31 23:59:59"; // 默认结束时间
$sql_end_time = "SELECT setting_value FROM settings WHERE setting_key = 'end_time'";
if ($sql_end_time) {
    $result_end_time = $conn->query($sql_end_time);
    if ($result_end_time && $result_end_time->num_rows > 0) {
        $end_time_value = $result_end_time->fetch_assoc()['setting_value'];
        if (!empty($end_time_value)) {
            $end_time_str = $end_time_value;
        }
    }
}

// 获取统计数据
$sql_stats = "SELECT 
    COUNT(*) as total_contestants, 
    (SELECT COUNT(*) FROM votes) as total_votes, 
    SUM(views) as total_views 
    FROM contestants WHERE status = 'approved'";
$result_stats = $conn->query($sql_stats);
$stats = $result_stats->fetch_assoc();
$total_contestants = $stats['total_contestants'] ?: 0;
$total_votes = $stats['total_votes'] ?: 0;
$total_views = $stats['total_views'] ?: 0;

// 计算倒计时
$end_date = strtotime($end_time_str);
$now = time();
$distance = $end_date - $now;
$days = floor($distance / (60 * 60 * 24));
$hours = floor(($distance % (60 * 60 * 24)) / (60 * 60));
$minutes = floor(($distance % (60 * 60)) / 60);
$seconds = floor($distance % 60);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport">
    <meta content="yes" name="apple-mobile-web-app-capable">
    <meta content="black" name="apple-mobile-web-app-status-bar-style">
    <meta content="telephone=no" name="format-detection">
    <title><?php echo htmlspecialchars($site_title); ?> - 活动奖品</title>
    <style>
        /* 添加CSS变量 */
        :root {
            --primary-color: #007AFF;
            --secondary-color: #FF9900;  /* 添加次要颜色变量 */
            --mid-gray: #e0e0e0;
            --dark-gray: #8E8E93;
            --text-secondary: #666666;   /* 添加次要文本颜色变量 */
        }

        body {
            margin: 0;
            padding: 0;
            font-family: "微软雅黑", Arial, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            padding-bottom: 60px; /* 为底部导航预留空间 */
        }
        
        /* 清除浮动 */
        .clearfix:after {
            content: "";
            display: table;
            clear: both;
        }
        
        /* 头部样式 */
        .m_head {
            background: #fff;
            padding: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        /* 统计数据样式 */
        .num_box {
            margin: 15px 0;
            background-color: #fff;
            border-radius: 5px;
            padding: 10px;
        }
        
        .num_box_ul {
            list-style: none;
            display: flex;
            justify-content: space-around;
            padding: 0;
            margin: 0;
        }
        
        .num_box_ul li {
            text-align: center;
        }
        
        /* 更新倒计时样式以匹配图片 */
        .countdown-container {
            background: linear-gradient(to right, #ff9500, #ff5722);
            padding: 15px;
            border-radius: 8px;
            color: white;
            text-align: center;
            margin: 15px 0;
        }
        
        .countdown-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .countdown-digits {
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 20px;
        }
        
        .countdown-digit {
            background: rgba(255,255,255,0.2);
            padding: 5px 10px;
            border-radius: 4px;
            margin: 0 4px;
            font-weight: bold;
        }
        
        .countdown-label {
            margin: 0 4px;
        }
        
        /* 奖品内容样式 */
        .bti {
            margin: 20px 10px;
            text-align: center;
        }
        
        .text img {
            max-width: 100%;
            height: auto;
        }
        
        /* 底部导航 */
        .tab-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            display: flex;
            z-index: 10000;
            border-top: 1px solid var(--mid-gray);
            padding: 8px 0 calc(8px + env(safe-area-inset-bottom, 0px));
        }
        
        .tab-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 6px 0;
            color: var(--dark-gray);
            font-size: 12px;
            transition: all 0.2s ease;
        }
        
        .tab-item.active {
            color: var(--primary-color);
        }
        
        .tab-icon {
            font-size: 22px;
            margin-bottom: 4px;
        }
        
        .tab-item:active {
            transform: scale(0.92);
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
        
        /* 返回顶部按钮 */
        #back-top {
            position: fixed;
            right: 15px;
            bottom: 70px;
            width: 36px;
            height: 36px;
            background: rgba(0,0,0,0.5);
            border-radius: 50%;
            text-align: center;
            line-height: 36px;
            color: #fff;
            text-decoration: none;
        }
        
        /* 倒计时样式 */
        .zdjs {
            text-align: center;
            margin: 15px 0;
            background: linear-gradient(to right, var(--secondary-color), #FF5E3A);
            padding: 15px;
            border-radius: 8px;
            color: white;
        }
        
        .h1 {
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 0.1em;
            margin-bottom: 8px;
            display: block;
        }
        
        .h2 strong {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 4px 8px;
            border-radius: 6px;
            margin: 0 2px;
            font-size: 20px;
            min-width: 36px;
            text-align: center;
        }
    </style>
</head>

<body>
    <!-- 页头部分 -->
    <header>
        <div class="m_head clearfix">
            <!-- 活动横幅 -->
            <a href="https://www.beihua.edu.cn/wxy/">
                <img src="http://longhua868.fss-my.addlink.cn//17423850687160410.jpg" style="height:auto;width:100%" alt="北华大学文学院">
            </a>
            
            <!-- 统计数据 -->
            <div class="num_box">
                <div class="newIndexbox1">
                    <ul class="num_box_ul">
                        <li>
                            <span style="font-size: 24px; color: #FF9900; font-weight: bold;"><?php echo number_format($total_contestants); ?></span>
                            <br>
                            <span class="text" style="font-size: 14px; color: #666;">选手数</span>
                        </li>
                        <li>
                            <span style="font-size: 24px; color: #FF9900; font-weight: bold;"><?php echo number_format($total_votes); ?></span>
                            <br>
                            <span class="text" style="font-size: 14px; color: #666;">累赞数</span>
                        </li>
                        <li>
                            <span style="font-size: 24px; color: #FF9900; font-weight: bold;"><?php echo number_format($total_views); ?></span>
                            <br>
                            <span class="text" style="font-size: 14px; color: #666;">浏览量</span>
                        </li>
                    </ul>
                    
                    <!-- 倒计时 -->
                    <div class="zdjs">
                        <span class="h1">距活动还剩:</span>
                        <span class="h2">
                            <strong id="DD"><?php echo $days; ?></strong> 天
                            <strong id="HH"><?php echo $hours; ?></strong> 时
                            <strong id="MM"><?php echo $minutes; ?></strong> 分
                            <strong id="SS"><?php echo $seconds; ?></strong> 秒
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- 奖品展示 -->
    <div class="bti bti11">
        <div class="text">
            <p><img src="http://longhua868.fss-my.addlink.cn//17412255653037502.jpg" alt="活动奖品"></p>
        </div>
    </div>
    
    <!-- 间隔 -->
    <p>&nbsp;</p>
    <p>&nbsp;</p>
    <p>&nbsp;</p>
    
    <!-- 底部导航 -->
    <div class="tab-bar">
        <div class="tab-item">
            <a href="/index.php">
                <div class="tab-icon">🏠</div>
                <div>首页</div>
            </a>
        </div>
        <div class="tab-item">
            <a href="/top.php">
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
            <a href="/signup.html">
                <div class="tab-icon">📝</div>
                <div>报名</div>
            </a>
        </div>
        <div class="tab-item active">
            <a href="/prize.php">
                <div class="tab-icon">🎁</div>
                <div>奖品</div>
            </a>
        </div>
    </div>
    
    <!-- 返回顶部按钮 -->
    <a href="#" id="back-top">↑</a>
    
    <!-- JavaScript 实时更新倒计时 -->
    <script>
        // 设置结束日期时间戳（PHP传入的结束时间）
        const endTime = <?php echo $end_date; ?> * 1000;
        
        // 更新倒计时函数
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = endTime - now;
            
            // 计算天、时、分、秒
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            // 更新DOM元素
            document.getElementById("DD").innerHTML = days;
            document.getElementById("HH").innerHTML = hours;
            document.getElementById("MM").innerHTML = minutes;
            document.getElementById("SS").innerHTML = seconds;
            
            // 如果倒计时结束，显示过期信息
            if (distance < 0) {
                clearInterval(countdownTimer);
                document.getElementById("DD").innerHTML = "0";
                document.getElementById("HH").innerHTML = "0";
                document.getElementById("MM").innerHTML = "0";
                document.getElementById("SS").innerHTML = "0";
            }
        }
        
        // 页面加载后立即更新一次
        updateCountdown();
        
        // 每秒更新一次倒计时
        const countdownTimer = setInterval(updateCountdown, 1000);
    </script>
</body>
</html>
<?php
// 关闭数据库连接
$conn->close();
?>
