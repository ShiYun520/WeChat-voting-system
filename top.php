<?php
// 数据库连接配置
function getDbConnection() {
    $host = DB_HOST;
    $dbname = DB_NAME;
    $username = DB_USER;
    $password = DB_PASS;
    
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        // 设置PDO错误模式为异常
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        echo "连接失败: " . $e->getMessage();
        die();
    }
}

// 定义数据库常量
define('DB_HOST', 'localhost');
define('DB_NAME', 'voting_system');
define('DB_USER', 'tp520');
define('DB_PASS', 'tp520');

// 获取系统统计数据
function getStats() {
    $conn = getDbConnection();
    
    // 获取选手数
    $stmt = $conn->prepare("SELECT COUNT(*) AS contestant_count FROM contestants WHERE status = 'approved'");
    $stmt->execute();
    $contestant_count = $stmt->fetchColumn();
    
    // 获取总投票数
    $stmt = $conn->prepare("SELECT COUNT(*) AS vote_count FROM votes");
    $stmt->execute();
    $vote_count = $stmt->fetchColumn();
    
    // 获取总浏览量
    $stmt = $conn->prepare("SELECT SUM(views) AS view_count FROM contestants");
    $stmt->execute();
    $view_count = $stmt->fetchColumn() ?? 0;
    
    return [
        'contestant_count' => $contestant_count,
        'vote_count' => $vote_count,
        'view_count' => $view_count
    ];
}

// 获取选手列表 - 修复LIMIT参数问题
function getContestants($page = 1, $perPage = 10, $search = '') {
    $conn = getDbConnection();
    
    $offset = ($page - 1) * $perPage;
    
    // 构建基本SQL
    $sql = "
        SELECT c.*, COUNT(v.id) AS votes 
        FROM contestants c
        LEFT JOIN votes v ON c.id = v.contestant_id
        WHERE c.status = 'approved'
    ";
    
    // 添加搜索条件
    if (!empty($search)) {
        $sql .= " AND (c.name LIKE :search OR c.number LIKE :search)";
    }
    
    // 完成SQL语句
    $sql .= " GROUP BY c.id ORDER BY votes DESC, c.created_at DESC LIMIT :offset, :limit";
    
    $stmt = $conn->prepare($sql);
    
    // 绑定参数 - 使用命名参数而不是位置参数
    if (!empty($search)) {
        $searchParam = "%$search%";
        $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
    }
    
    // 明确将LIMIT参数绑定为整数
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
    
    $stmt->execute();
    $contestants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $contestants;
}

// 获取页码，默认为第1页
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10; // 每页显示数量

// 获取搜索关键词（如果有）
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 获取选手列表（第4名开始）
$contestants = getContestants($page, $per_page, $search);

// 获取前三名选手
$top3 = getContestants(1, 3, '');

// 获取系统统计数据
$stats = getStats();

// 计算总页数 - 修复查询
$conn = getDbConnection();
$count_query = "SELECT COUNT(*) as total FROM contestants WHERE status = 'approved'";
if (!empty($search)) {
    $count_query .= " AND (name LIKE :search OR number LIKE :search)";
    $stmt = $conn->prepare($count_query);
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    $stmt->execute();
} else {
    $stmt = $conn->query($count_query);
}
$total_rows = $stmt->fetchColumn();
$total_pages = ceil($total_rows / $per_page);
?>


<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="format-detection" content="telephone=no">
    <title>活动排行榜</title>
    <style>
        /* ... 保留原有样式 ... */
        body {
            margin: 0;
            padding: 0;
            font-family: "Helvetica Neue", Helvetica, Arial, "PingFang SC", "Hiragino Sans GB", "Heiti SC", "Microsoft YaHei", "WenQuanYi Micro Hei", sans-serif;
            background-color: #f8f9fa;
            color: #333;
            padding-bottom: 70px;
        }

        .header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            text-align: center;
            padding: 20px 0;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .rank300 {
            margin: 15px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        /* 前三名奖杯样式区 */
        .top-three {
            background-color: #5dcbf5;
            padding: 20px 0 0 0;
            position: relative;
            text-align: center;
            margin-bottom: 20px;
            border-radius: 10px 10px 0 0;
            overflow: hidden;
        }
        
        .stars span {
            position: absolute;
            color: white;
            opacity: 0.7;
        }
        
        .trophies {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            position: relative;
            z-index: 1;
            padding-bottom: 10px;
        }
        
        .trophy {
            text-align: center;
            margin: 0 10px;
        }
        
        .trophy.first {
            position: relative;
            top: -30px;
        }
        
        .cup {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto;
            position: relative;
            font-weight: bold;
            color: white;
        }
        
        .trophy.first .cup {
            width: 120px;
            height: 120px;
            background-color: #ffc107;
            font-size: 60px;
            border-radius: 15px 15px 70px 70px;
        }
        
        .trophy.second .cup {
            width: 100px;
            height: 100px;
            background-color: #d6d6d6;
            font-size: 50px;
            border-radius: 15px 15px 60px 60px;
        }
        
        .trophy.third .cup {
            width: 100px;
            height: 100px;
            background-color: #cd7f32;
            font-size: 50px;
            border-radius: 15px 15px 60px 60px;
        }
        
        .handle {
            position: absolute;
            background-color: inherit;
        }
        
        .trophy.first .handle.left {
            left: -18px;
            top: 36px;
            width: 18px;
            height: 48px;
            border-radius: 10px 0 0 10px;
        }
        
        .trophy.first .handle.right {
            right: -18px;
            top: 36px;
            width: 18px;
            height: 48px;
            border-radius: 0 10px 10px 0;
        }
        
        .trophy.second .handle.left,
        .trophy.third .handle.left {
            left: -15px;
            top: 30px;
            width: 15px;
            height: 40px;
            border-radius: 10px 0 0 10px;
        }
        
        .trophy.second .handle.right,
        .trophy.third .handle.right {
            right: -15px;
            top: 30px;
            width: 15px;
            height: 40px;
            border-radius: 0 10px 10px 0;
        }
        
        .base {
            background-color: #8c7853;
            margin: 5px auto;
        }
        
        .trophy.first .base {
            width: 80px;
            height: 25px;
        }
        
        .trophy.second .base,
        .trophy.third .base {
            width: 70px;
            height: 20px;
        }
        
        .podium {
            background-color: #fff;
            display: flex;
            justify-content: space-around;
            position: relative;
            z-index: 1;
            padding: 20px 0;
            border-radius: 0 0 10px 10px;
        }
        
        .winner {
            width: 33%;
            text-align: center;
            padding: 0 10px;
        }
        
        .winner .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 10px;
            border: 2px solid #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .winner .name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .winner .vote-label {
            font-size: 14px;
            color: #666;
        }
        
        .winner .vote-count {
            font-size: 24px;
            font-weight: bold;
            color: #FF9900;
        }

        .list1 {
            padding: 15px;
            border-bottom: 1px solid #eee;
            position: relative;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .list1:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .list1 a {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #333;
        }

        .list1 .img {
            flex: 0 0 60px;
            margin-right: 15px;
        }

        .list1 .img img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .list1 .name {
            flex: 1;
            font-size: 18px;
            font-weight: bold;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .list1 .zan1 {
            font-size: 14px;
            color: #666;
            margin-right: 5px;
        }

        .list1 .zan {
            font-size: 20px;
            font-weight: bold;
            color: #FF9900;
        }

        .list {
            display: flex;
            align-items: center;
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
            transition: all 0.3s ease;
        }

        .list:hover {
            background-color: #f8f9fa;
        }

        .list a {
            display: flex;
            align-items: center;
            width: 100%;
            text-decoration: none;
            color: #333;
        }

        .list span {
            margin-right: 10px;
        }

        .list img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .pagination {
            text-align: center;
            margin: 30px 0;
        }

        .pagination ul {
            display: inline-block;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .pagination li {
            display: inline-block;
            margin: 0 5px;
        }

        .pagination a {
            display: block;
            padding: 8px 12px;
            background: #fff;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
        }

        .pagination .active a,
        .pagination a:hover {
            background: #2575fc;
            color: #fff;
        }

        .search-box {
            margin: 15px;
            display: flex;
            justify-content: center;
        }
        
        .search-box input {
            width: 70%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px 0 0 5px;
            outline: none;
            font-size: 16px;
        }
        
        .search-box button {
            padding: 10px 15px;
            background: #2575fc;
            color: white;
            border: none;
            border-radius: 0 5px 5px 0;
            cursor: pointer;
            font-size: 16px;
        }
        
        .bot_main {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: #fff;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 10000;
        }

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
        
        #back-top {
            position: fixed;
            right: 20px;
            bottom: 80px;
            width: 40px;
            height: 40px;
            background: rgba(37, 117, 252, 0.8);
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        #back-top:hover {
            background: rgba(37, 117, 252, 1);
        }
        
        .rotate {
            width: 30px;
            height: 30px;
            background-size: 100% 100%;
            background-image: url("http://pic3.blkjyi.cn//tpl/static/vote/index4/music_off.png");
            -webkit-animation: rotating 1.2s linear infinite;
            animation: rotating 1.2s linear infinite;
        }
        
        @keyframes rotating {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .offb {
            width: 30px;
            height: 30px;
            background-size: 100% 100%;
            background-image: url("http://pic3.blkjyi.cn//tpl/static/vote/index4/music_no.png");
        }
        
        .stats-box {
            display: flex;
            justify-content: space-around;
            background: white;
            padding: 15px;
            margin: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2575fc;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>

    <section id="ranking">
        <!-- 新的前三名奖杯设计 -->
        <div class="top-three">
            <!-- 星星装饰 -->
            <div class="stars">
                <span class="star" style="top: 10%; left: 10%; font-size: 16px;">✦</span>
                <span class="star" style="top: 20%; left: 30%; font-size: 14px;">✦</span>
                <span class="star" style="top: 5%; left: 50%; font-size: 18px;">✦</span>
                <span class="star" style="top: 15%; left: 70%; font-size: 16px;">✦</span>
                <span class="star" style="top: 25%; left: 85%; font-size: 14px;">✦</span>
                <span class="star" style="top: 30%; left: 20%; font-size: 16px;">✦</span>
                <span class="star" style="top: 35%; left: 60%; font-size: 18px;">✦</span>
                <span class="star" style="top: 15%; left: 40%; font-size: 14px;">✦</span>
            </div>
            
            <!-- 奖杯展示区 -->
            <div class="trophy-image" style="text-align: center; padding: 10px 0;">
                <img src="http://pic3.blkjyi.cn//tpl/static/vote/index10/t13.png" style="max-width: 100%; width: 130%; height: auto; display: block; margin: 0 auto;">
            </div>

            <!-- 底座区域 - 动态生成前三名 -->
            <div class="podium">
                <?php
                // 初始化三个位置的变量
                $first = null;
                $second = null;
                $third = null;
                
                // 获取前三名数据
                if (count($top3) > 0) {
                    $first = isset($top3[0]) ? $top3[0] : null;
                    $second = isset($top3[1]) ? $top3[1] : null;
                    $third = isset($top3[2]) ? $top3[2] : null;
                }
                
                // 显示第二名(左侧)
                if ($second): 
                ?>
                <div class="winner">
                    <a href="detail.php?id=<?php echo $second['id']; ?>" style="text-decoration: none; color: #333;">
                        <div class="avatar">
                            <img src="<?php echo htmlspecialchars($second['cover_photo']); ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="<?php echo htmlspecialchars($second['name']); ?>">
                        </div>
                        <div class="name"><?php echo htmlspecialchars($second['name']); ?>(<?php echo htmlspecialchars($second['number']); ?>号)</div>
                        <div class="vote-label">点赞数</div>
                        <div class="vote-count"><?php echo $second['votes']; ?></div>
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- 第一名选手(中间) -->
                <?php if ($first): ?>
                <div class="winner">
                    <a href="detail.php?id=<?php echo $first['id']; ?>" style="text-decoration: none; color: #333;">
                        <div class="avatar">
                            <img src="<?php echo htmlspecialchars($first['cover_photo']); ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="<?php echo htmlspecialchars($first['name']); ?>">
                        </div>
                        <div class="name"><?php echo htmlspecialchars($first['name']); ?>(<?php echo htmlspecialchars($first['number']); ?>号)</div>
                        <div class="vote-label">点赞数</div>
                        <div class="vote-count"><?php echo $first['votes']; ?></div>
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- 第三名选手(右侧) -->
                <?php if ($third): ?>
                <div class="winner">
                    <a href="detail.php?id=<?php echo $third['id']; ?>" style="text-decoration: none; color: #333;">
                        <div class="avatar">
                            <img src="<?php echo htmlspecialchars($third['cover_photo']); ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="<?php echo htmlspecialchars($third['name']); ?>">
                        </div>
                        <div class="name"><?php echo htmlspecialchars($third['name']); ?>(<?php echo htmlspecialchars($third['number']); ?>号)</div>
                        <div class="vote-label">点赞数</div>
                        <div class="vote-count"><?php echo $third['votes']; ?></div>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="rank300" id="top300">
          <!-- 排名列表 4-N -->
<ul style="margin: 20px 0 0 0; padding: 0; list-style: none; width: 100%;">
    <?php 
    // 如果是第一页，我们需要明确跳过前三名选手
    if ($page == 1) {
        // 从第4个选手开始显示
        $display_contestants = array_slice($contestants, 3);
        $rank = 4; // 排名从4开始
    } else {
        // 第二页及以后的正常显示
        $display_contestants = $contestants;
        $rank = ($page - 1) * $per_page + 1; // 计算起始排名
    }
    
    if (count($display_contestants) > 0) {
        foreach($display_contestants as $contestant) {
    ?>
    <li class="list">
        <a href="detail.php?id=<?php echo $contestant['id']; ?>">
            <span style="width:20%; color:#ee722d">NO.<?php echo $rank; ?></span>
            <span style="width:12%;">
                <img src="<?php echo htmlspecialchars($contestant['cover_photo']); ?>" alt="<?php echo htmlspecialchars($contestant['name']); ?>">
            </span>
            <span style="width:48%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?php echo htmlspecialchars($contestant['name']); ?>(<?php echo htmlspecialchars($contestant['number']); ?>号)</span>
            <span style="width:20%;color:#7d7a7b"><?php echo $contestant['votes']; ?>赞</span>
        </a>
    </li>
    <?php
            $rank++; // 增加排名计数器
        }
    } else {
        echo '<li class="no-data" style="text-align:center; padding:20px;">暂无更多选手数据</li>';
    }
    ?>
</ul>


        </div>
        
        <!-- 分页 -->
        <div class="pagination">
            <ul>
                <?php
                // 显示分页链接
                $pagination_range = 5; // 显示几个分页按钮
                $start_page = max(1, $page - floor($pagination_range/2));
                $end_page = min($total_pages, $start_page + $pagination_range - 1);
                
                // 添加搜索参数到分页链接
                $search_param = !empty($search) ? "&search=".urlencode($search) : "";
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $active_class = ($i == $page) ? 'class="active"' : '';
                    echo "<li $active_class><a href='top.php?page=$i$search_param'>$i</a></li>";
                }
                ?>
            </ul>
        </div>
    </section>
    
    <!-- 底部导航 -->
    <div class="tab-bar">
        <div class="tab-item">
            <a href="index.php">
                <div class="tab-icon">🏠</div>
                <div>首页</div>
            </a>
        </div>
        <div class="tab-item active">
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
    
    <!-- 返回顶部按钮 -->
    <a href="#" id="back-top">↑</a>
    
    <!-- JavaScript -->
    <script type="text/javascript" src="http://pic3.blkjyi.cn//tpl/static/vote/index10/jquery-2.1.3.min.js"></script>
    <script>
        // 返回顶部功能
        $(function() {
            // 先将#back-top隐藏
            $('#back-top').hide();
            // 当滚动条的垂直位置距顶部100像素以下时，跳转链接出现，否则消失
            $(window).scroll(function() {
                if ($(window).scrollTop() > 100) {
                    $('#back-top').fadeIn(1000);
                } else {
                    $("#back-top").fadeOut(1000);
                }
            });
            // 点击跳转链接，滚动条跳到0的位置，页面移动速度是1000
            $("#back-top").click(function() {
                $('body,html').animate({
                    scrollTop: 0
                }, 1000);
                return false; // 防止默认事件行为
            });
        });
    </script>
</body>
</html>
