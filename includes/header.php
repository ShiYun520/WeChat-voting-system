<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : '红文之光 照亮童年振兴梦'; ?></title>
    <meta name="description" content="<?php echo isset($pageDescription) ? $pageDescription : '青岛农业大学人文社会科学学院主办的"红文之光 照亮童年振兴梦"富体月榜挑战赛内公益主题活动'; ?>">
    
    <?php if(isset($pageImage)): ?>
    <meta property="og:image" content="<?php echo $pageImage; ?>">
    <?php endif; ?>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft YaHei", "微软雅黑", sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            padding-bottom: 70px; /* 为底部导航留出空间 */
        }
        
        .banner {
            position: relative;
            width: 100%;
            height: 300px;
            background: linear-gradient(to bottom, #8c2e2e, #922424);
            overflow: hidden;
            color: #fff;
            padding: 20px;
            text-align: center;
        }
        
        .banner-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(129, 39, 39, 0.9);
            z-index: 1;
        }
        
        .banner-content {
            position: relative;
            z-index: 2;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .banner-title {
            font-size: 36px;
            font-weight: bold;
            margin: 30px 0 15px;
            color: #f9e4bf;
        }
        
        .banner-subtitle {
            font-size: 24px;
            margin-bottom: 20px;
            color: #f9e4bf;
        }
        
        .banner-text {
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        /* 全局样式 */
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            padding: 20px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
            border-left: 4px solid #c14a43;
            padding-left: 10px;
        }
        
        .btn {
            display: inline-block;
            height: 40px;
            line-height: 40px;
            padding: 0 20px;
            background-color: #c14a43;
            color: #fff;
            border: none;
            border-radius: 20px;
            font-size: 16px;
            text-decoration: none;
            cursor: pointer;
            text-align: center;
        }
        
        .btn-secondary {
            background-color: #666;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #c14a43;
            color: #c14a43;
        }
        
        /* 底部导航条 */
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
            color: #c14a43;
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
        
        /* 通知提示 */
        .toast-message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(-20px);
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 14px;
            z-index: 100;
            opacity: 0;
            transition: opacity 0.3s, transform 0.3s;
        }
        
        .toast-message.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    </style>
    
    <?php if(isset($extraStyles)): ?>
    <style>
        <?php echo $extraStyles; ?>
    </style>
    <?php endif; ?>
</head>
<body>
    <?php if(!isset($hideCommonBanner) || !$hideCommonBanner): ?>
    <div class="banner">
        <div class="banner-bg"></div>
        <div class="banner-content">
            <h1 class="banner-title">红文之光</h1>
            <h2 class="banner-subtitle">照亮童年振兴梦</h2>
            <p class="banner-text">——富体月榜挑战赛内公益主题活动</p>
            <p>青岛农业大学人文社会科学学院</p>
        </div>
    </div>
    <?php endif; ?>
