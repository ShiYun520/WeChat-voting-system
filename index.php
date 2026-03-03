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

// 处理搜索
$search_keyword = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["keyword"])) {
    $search_keyword = $_POST["keyword"];
    header("Location: search.php?keyword=" . urlencode($search_keyword));
    exit();
}

// 获取排序方式
$order = isset($_GET['order']) ? $_GET['order'] : 'top';

// 分页
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 8;
$offset = ($page - 1) * $items_per_page;

// 根据排序查询选手
if ($order == 'new') {
    $sql = "SELECT c.*, COUNT(DISTINCT v.id) AS votes FROM contestants c 
            LEFT JOIN votes v ON c.id = v.contestant_id 
            WHERE c.status = 'approved' 
            GROUP BY c.id 
            ORDER BY c.id DESC LIMIT $offset, $items_per_page";
} else {
    $sql = "SELECT c.*, COUNT(DISTINCT v.id) AS votes FROM contestants c 
            LEFT JOIN votes v ON c.id = v.contestant_id 
            WHERE c.status = 'approved' 
            GROUP BY c.id 
            ORDER BY votes DESC LIMIT $offset, $items_per_page";
}

$result = $conn->query($sql);

// 计算总页数
$sql_count = "SELECT COUNT(*) as total FROM contestants WHERE status = 'approved'";
$result_count = $conn->query($sql_count);
$row_count = $result_count->fetch_assoc();
$total_pages = ceil($row_count['total'] / $items_per_page);

// 获取精选选手数据
$sql_featured = "SELECT c.*, COUNT(DISTINCT v.id) AS votes FROM contestants c 
                LEFT JOIN votes v ON c.id = v.contestant_id 
                WHERE c.status = 'approved' 
                GROUP BY c.id 
                ORDER BY votes DESC LIMIT 1";
$result_featured = $conn->query($sql_featured);
$featured_contestant = null;
if ($result_featured && $result_featured->num_rows > 0) {
    $featured_contestant = $result_featured->fetch_assoc();
}

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
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <title><?php echo htmlspecialchars($site_title); ?></title>
  
    <style>
    /* 苹果风格基础样式 */
    @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500;700&display=swap');
    
    :root {
        --primary-color: #007AFF;
        --secondary-color: #FF9500;
        --success-color: #34C759;
        --danger-color: #FF3B30;
        --warning-color: #FFCC00;
        --info-color: #5AC8FA;
        --light-gray: #F2F2F7;
        --mid-gray: #E5E5EA;
        --dark-gray: #8E8E93;
        --text-color: #000000;
        --text-secondary: #6c6c6c;
        --border-radius: 12px;
        --small-radius: 8px;
        --card-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        -webkit-tap-highlight-color: transparent;
    }
    
    body {
        margin: 0;
        padding: 0;
        font-family: 'Noto Sans SC', -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Helvetica Neue', sans-serif;
        background-color: var(--light-gray);
        color: var(--text-color);
        padding-bottom: 70px;
        line-height: 1.5;
    }
    
    a {
        text-decoration: none;
        color: var(--primary-color);
        transition: all 0.2s ease;
    }
    
    /* 头部样式 */
    .m_head {
        background-color: #fff;
        padding: 15px;
        margin-bottom: 10px;
        box-shadow: var(--card-shadow);
    }
    
    .m_head img {
        border-radius: var(--small-radius);
        width: 100%;
        height: auto;
        display: block;
    }
    
    .clearfix:after {
        content: "";
        display: table;
        clear: both;
    }
    
    /* 数字盒子样式 */
    .num_box {
        margin: 15px 0;
        background-color: #fff;
        border-radius: var(--border-radius);
        padding: 15px;
        box-shadow: var(--card-shadow);
    }
    
    .num_box_ul {
        list-style: none;
        display: flex;
        padding: 0;
        margin: 0;
        justify-content: space-around;
    }
    
    .num_box_ul li {
        text-align: center;
    }
    
    /* 倒计时样式 */
    .zdjs {
        text-align: center;
        margin: 15px 0;
        background: linear-gradient(to right, var(--secondary-color), #FF5E3A);
        padding: 15px;
        border-radius: var(--border-radius);
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
    
    /* 精选选手展示 - 基于500×264px尺寸的自适应版本 */
.jrzx {
    position: relative;
    width: 100%;
    height: 0;
    padding-bottom: 52.8%; /* 264/500 = 0.528 或 52.8% - 保持宽高比 */
    margin: 15px 0;
    background-color: #fff;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--card-shadow);
}

.jrzx a {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: block;
}

.jrzx .img {
    position: absolute;
    height: calc(100% - 20px);
    width: 36%;
    right: 20px;
    transform: translateX(-10px); /* 正值向右移动，负值向左移动 */
    top: 10px;
    border-radius: var(--small-radius);
    overflow: hidden;
    z-index: 1;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.jrzx .img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.jrzx .name {
    position: absolute;
    left: 50%; /* 改为50%，从左边距离为容器宽度的一半 */
    top: 50%; /* 垂直居中 */
    transform: translate(-50%, -50%); /* 同时修正水平和垂直居中 */
    transform: translateX(-25px); /* 正值向右移动，负值向左移动 */
    margin-top: -8px;
    color: #fff;
    font-size: 24px;
    font-weight: 600;
    text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
    z-index: 3;
    width: calc(64% - 70px); /* 64% = 100% - 36%(图片宽度) */
    white-space: nowrap;
    text-overflow: ellipsis;
    overflow: hidden;
    text-align: center; /* 确保文本内容也居中显示 */
}


.jrzxb {
    width: 100%; 
    height: 100%;
    position: absolute;
    top: 0;
    left: 0;
    z-index: 2;
    background-size: cover !important;
    background-position: center !important;
}

/* 响应式布局调整 */
@media (max-width: 767px) {
    .jrzx .name {
        font-size: 20px;
        left: 40px;
    }
}

@media (max-width: 480px) {
    .jrzx .name {
        font-size: 18px;
        left: 30px;
    }
    
    .jrzx .img {
        width: 40%; /* 稍微调大在小屏幕上的比例 */
        right: 15px;
    }
}

@media (max-width: 320px) {
    .jrzx .name {
        font-size: 16px;
        left: 20px;
    }
    
    .jrzx .img {
        width: 45%;
        right: 10px;
    }
}

    
    /* 报名按钮 */
    .baoming {
        display: block;
        text-align: center;
        background-color: var(--primary-color);
        color: white;
        padding: 16px;
        border-radius: var(--border-radius);
        margin: 15px 0;
        font-size: 18px;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 122, 255, 0.2);
        font-weight: 500;
    }
    
    .baoming:hover, .baoming:active {
        background-color: #0062cc;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 122, 255, 0.3);
    }
    
    /* 搜索框 */
    .search {
        margin: 15px 0;
    }
    
    .search_con {
        display: flex;
        border-radius: var(--border-radius);
        overflow: hidden;
        background: #fff;
        box-shadow: var(--card-shadow);
    }
    
    .text_box {
        flex: 1;
    }
    
    .text_box input {
        width: 100%;
        padding: 15px;
        border: none;
        outline: none;
        font-size: 16px;
        font-family: inherit;
    }
    
    .btn input {
        background-color: var(--primary-color);
        color: white;
        border: none;
        padding: 15px 20px;
        cursor: pointer;
        transition: background-color 0.3s ease;
        font-size: 16px;
        font-family: inherit;
        font-weight: 500;
    }
    
    .btn input:hover {
        background-color: #0062cc;
    }
    
    /* 选手排名 */
    .content {
        padding: 15px;
    }
    
    .h1 {
        display: block;
        text-align: center;
        margin: 20px 0;
        font-weight: 600;
    }
    
    .text_a {
        display: flex;
        justify-content: center;
        margin: 20px 0;
    }
    
    .text_a a {
        padding: 10px 20px;
        margin: 0 10px;
        background-color: #fff;
        border-radius: 20px;
        text-decoration: none;
        color: var(--text-secondary);
        transition: all 0.3s ease;
        box-shadow: var(--card-shadow);
        font-weight: 500;
    }
    
    .text_a a:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .text_a a.active {
        background-color: var(--primary-color);
        color: white;
        box-shadow: 0 4px 12px rgba(0, 122, 255, 0.2);
    }
    
    .blank20 {
        height: 20px;
    }
    
    /* 选手列表 */
    .list_box {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
    }
    
    /* 选手卡片样式 */
    .picCon {
        width: 48%;
        margin-bottom: 20px;
        position: relative;
        border-radius: var(--border-radius);
        overflow: hidden;
        background: #fff;
        box-shadow: var(--card-shadow);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .picCon:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }
    
    .picCon a {
        color: inherit;
        text-decoration: none;
    }
    
    .person-image {
        width: 100%;
        height: 0;
        padding-bottom: 130%;
        background-size: cover;
        background-position: center;
        position: relative;
    }
    
    .number {
        position: absolute;
        top: 10px;
        left: 10px;
        background-color: rgba(255,255,255,0.8);
        color: var(--text-color);
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 14px;
        font-weight: 600;
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    
    .person-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px;
        border-top: 1px solid var(--mid-gray);
    }
    
    .name {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-color);
        margin: 0;
    }
    
    .zan {
        font-size: 16px;
        font-weight: 600;
        color: var(--secondary-color);
        margin: 0;
    }
    
    /* 投票按钮 */
    .vote {
        display: block;
        width: 100%;
        background-color: var(--primary-color);
        color: white !important;
        padding: 12px 0;
        text-align: center;
        text-decoration: none;
        font-size: 16px;
        font-weight: 500;
        margin: 0;
        border: none;
        transition: background-color 0.3s ease;
    }
    
    .vote:hover, .vote:active {
        background-color: #0062cc;
    }
    
    /* 底部导航 */
    .bot_main {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        background-color: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        z-index: 10000;
        border-top: 1px solid var(--mid-gray);
    }
    
    .bot_main ul {
        display: flex;
        list-style: none;
        margin: 0;
        padding: 10px 0;
    }
    
    .bot_main li {
        flex: 1;
        text-align: center;
    }
    
    .bot_main a {
        text-decoration: none;
        color: var(--dark-gray);
        display: block;
    }
    
    .bot_main .ico {
        display: block;
    }
    
    .bot_main .ico img {
        width: 24px;
        height: 24px;
        display: block;
        margin: 0 auto 5px;
        opacity: 0.7;
        transition: opacity 0.2s ease;
    }
    
    .bot_main .txt {
        font-size: 12px;
        color: var(--dark-gray);
        transition: color 0.2s ease;
    }
    
    .bot_main .bbg {
        color: var(--primary-color);
    }
    
    .bot_main .bbg .ico img {
        opacity: 1;
    }
    
    .bot_main .bbg .txt {
        color: var(--primary-color);
        font-weight: 500;
    }
    
    /* 分页 */
    .pagination {
        text-align: center;
        margin: 25px 0;
    }
    
    .pagination ul {
        display: inline-flex;
        list-style: none;
        padding: 0;
    }
    
    .pagination li {
        margin: 0 3px;
    }
    
    .pagination a {
        display: block;
        padding: 8px 12px;
        background-color: #fff;
        border-radius: 8px;
        text-decoration: none;
        color: var(--text-color);
        box-shadow: var(--card-shadow);
        transition: all 0.2s ease;
    }
    
    .pagination li.active a {
        background-color: var(--primary-color);
        color: white;
        box-shadow: 0 4px 12px rgba(0, 122, 255, 0.2);
    }
    
    .pagination a:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    /* 免责声明 */
    .mzsm {
        text-align: center;
        margin: 25px 0 80px;
        width: 100%;
        float: left;
    }
    
    .mzsm a {
        color: var(--text-secondary);
        padding: 10px 20px;
        border-radius: var(--small-radius);
        background-color: #fff;
        text-decoration: none;
        transition: all 0.2s ease;
        box-shadow: var(--card-shadow);
        font-size: 14px;
    }
    
    .mzsm a:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    /* 投诉按钮 */
    .ts1 {
        width: 44px;
        height: 44px;
        background: rgba(255, 255, 255, 0.9);
        border-radius: 50%;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        margin: 0 0 10px 0;
        text-align: center;
        position: fixed;
        right: 15px;
        top: 160px;
        z-index: 9999;
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        border: 1px solid var(--mid-gray);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }
    
    .ts1:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.2);
    }
    
    .ts1 a {
        font-size: 14px;
        color: var(--text-color);
        line-height: 44px;
        text-align: center;
        text-decoration: none;
        font-weight: 500;
        width: 100%;
        height: 100%;
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
    </style>
</head>

<body>
    <!-- 页头 -->
    <header>
        <div class="m_head clearfix">
            <a href="https://www.beihua.edu.cn/wxy/">
                <img src="http://longhua868.fss-my.addlink.cn//17423850687160410.jpg" alt="北华大学文学院" style="width:100%; height:auto">
            </a>
            <br>
          
            <!-- 统计数据显示 -->
            <div class="num_box">
                <ul class="num_box_ul">
                    <li>
                        <span style="font-size: 24px; color: var(--secondary-color); font-weight: 600;"><?php echo $total_contestants; ?></span>
                        <br>
                        <span class="text" style="font-size: 14px; color: var(--text-secondary);">选手数</span>
                    </li>
                    <li>
                        <span style="font-size: 24px; color: var(--secondary-color); font-weight: 600;"><?php echo $total_votes; ?></span>
                        <br>
                        <span class="text" style="font-size: 14px; color: var(--text-secondary);">累赞数</span>
                    </li>
                    <li>
                        <span style="font-size: 24px; color: var(--secondary-color); font-weight: 600;"><?php echo $total_views; ?></span>
                        <br>
                        <span class="text" style="font-size: 14px; color: var(--text-secondary);">浏览量</span>
                    </li>
                </ul>
                
                <!-- 倒计时显示 -->
                <span class="zdsj" style="padding: 15px 0;">
                    <div class="zdjs">
                        <span class="h1">距活动还剩:</span>
                        <span class="h2">
                            <strong id="DD"><?php echo $days; ?></strong> 天
                            <strong id="HH"><?php echo $hours; ?></strong> 时
                            <strong id="MM"><?php echo $minutes; ?></strong> 分
                            <strong id="SS"><?php echo $seconds; ?></strong> 秒
                        </span>
                    </div>
                </span>
            </div>
          
            <!-- 精选选手 -->
            <div class="jrzx">
                <?php if ($featured_contestant): ?>
                <a href="detail.php?id=<?php echo $featured_contestant['id']; ?>">
                    <div class="jrzxb" style="background:url(http://pic3.blkjyi.cn//tpl/static/vote/index10/syjrzx.png) no-repeat;"></div>
                    <div class="img"><img src="<?php echo htmlspecialchars($featured_contestant['cover_photo']); ?>" alt="<?php echo htmlspecialchars($featured_contestant['name']); ?>" /></div>
                    <div class="name"><?php echo htmlspecialchars($featured_contestant['name']); ?></div>
                </a>
                <?php endif; ?>
            </div>
          
            <!-- 报名按钮 -->
            <a href="signup.html" class="baoming">
                <div class="mlh1">
                    <span class="not2">我要报名</span>
                </div>
            </a>
          
           <!-- 搜索框 -->
<div class="search">
    <form action="search.php" method="post" id="searchForm">
        <div class="search_con">
            <div class="text_box">
                <input type="search" id="searchText" value="<?php echo htmlspecialchars($search_keyword); ?>" name="keyword" placeholder="请输入选手编号" autocomplete="off">
            </div>
            <div class="btn">
                <input type="submit" name="seachid" id="searchBtn" value="搜索">
            </div>
        </div>
    </form>
</div>
</div>
                </form>
            </div>
        </div>
    </header>
  
    <!-- 主内容区 -->
    <section class="content">
        <span class="h1" style="font-size: 20px;">选手排名</span>
      
        <!-- 排序选项 -->
        <div class="text_a clearfix" id="sort">
            <a href="index.php?order=top" class="<?php echo ($order == 'top' || $order == '') ? 'active' : ''; ?>">排行选手</a>
            <a href="index.php?order=new" class="<?php echo ($order == 'new') ? 'active' : ''; ?>">最新选手            </a>
        </div>
      
        <div class="blank20"></div>
      
        <!-- 选手列表 -->
        <!-- 选手列表 -->
<div id="pageCon" class="match_page" style="padding-bottom: 10px">
    <ul class="list_box clearfix">
        <?php 
        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
        ?>
        <li class="picCon">
            <a href="detail.php?id=<?php echo $row['id']; ?>">
                <div class="person-image" style="background-image:url(<?php echo htmlspecialchars($row['cover_photo']); ?>)">
                    <div class="number"><?php echo htmlspecialchars($row['number']); ?>号</div>
                </div>
                <div class="person-info">
                    <span class="name"><?php echo htmlspecialchars($row['name']); ?></span>
                    <span class="zan"><?php echo $row['votes']; ?>赞</span>
                </div>
            </a>
            <a href="detail.php?id=<?php echo $row['id']; ?>" class="vote">为TA点赞</a>
        </li>
        <?php 
            }
        } else {
            echo "<p style='text-align:center'>暂无选手数据</p>";
        }
        ?>
    </ul>
</div>
        <br>
      
        <!-- 分页 -->
        <div class="pagination pagination-centered">
            <ul>
                <?php
                // 生成分页链接
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($page > 1) {
                    echo '<li><a href="index.php?order=' . $order . '&page=' . ($page - 1) . '">&lt;</a></li>';
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    echo '<li ' . ($i == $page ? 'class="active"' : '') . '><a href="index.php?order=' . $order . '&page=' . $i . '">' . $i . '</a></li>';
                }
                
                if ($page < $total_pages) {
                    echo '<li><a href="index.php?order=' . $order . '&page=' . ($page + 1) . '">&gt;</a></li>';
                }
                
                if ($end_page < $total_pages) {
                    echo '<li><a href="index.php?order=' . $order . '&page=' . $total_pages . '">&gt;&gt;</a></li>';
                }
                ?>
            </ul>
        </div>
    </section>
  
    <!-- 投诉按钮 -->
    <div class="ts1">
        <a href="feedback.php" id="share">投诉</a>
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
            <a href="prize.php">
                <div class="tab-icon">🎁</div>
                <div>奖品</div>
            </a>
        </div>
    </div>

    <script>
        // 倒计时功能
        function updateCountdown() {
            const endDate = new Date("<?php echo date('Y-m-d H:i:s', $end_date); ?>").getTime();
            const now = new Date().getTime();
            const distance = endDate - now;
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            document.getElementById("DD").innerHTML = days;
            document.getElementById("HH").innerHTML = hours;
            document.getElementById("MM").innerHTML = minutes;
            document.getElementById("SS").innerHTML = seconds;
        }
        
        // 每秒更新一次
        setInterval(updateCountdown, 1000);
        // 立即执行一次
        updateCountdown();
    </script>
    <!-- 添加JavaScript代码 -->
<script>
document.getElementById('searchForm').addEventListener('submit', function(event) {
    // 获取搜索框的值
    var searchValue = document.getElementById('searchText').value.trim();
    
    // 判断输入值是否是纯数字（假设ID是纯数字格式）
    if (/^\d+$/.test(searchValue)) {
        // 如果是纯数字，阻止表单默认提交
        event.preventDefault();
        
        // 直接跳转到选手详情页
        window.location.href = 'detail.php?id=' + searchValue;
    }
    // 如果不是纯数字，表单会正常提交到search.php
});
</script>
</body>
</html>
<?php
// 关闭数据库连接
$conn->close();
?>

