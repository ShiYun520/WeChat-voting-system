    <!-- 页面内容结束 -->
    
    <!-- 底部导航栏 -->
    <div class="tab-bar">
        <div class="tab-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
            <a href="/index.php">
                <div class="tab-icon">🏠</div>
                <div>首页</div>
            </a>
        </div>
        <div class="tab-item <?php echo (basename($_SERVER['PHP_SELF']) == 'top.html') ? 'active' : ''; ?>">
            <a href="/top.html">
                <div class="tab-icon">🏆</div>
                <div>排行榜</div>
            </a>
        </div>
        <div class="tab-item <?php echo (basename($_SERVER['PHP_SELF']) == 'search.html') ? 'active' : ''; ?>">
            <a href="/search.html">
                <div class="tab-icon">🔍</div>
                <div>搜索</div>
            </a>
        </div>
        <div class="tab-item <?php echo (basename($_SERVER['PHP_SELF']) == 'signup.html') ? 'active' : ''; ?>">
            <a href="/signup.html">
                <div class="tab-icon">📝</div>
                <div>报名</div>
            </a>
        </div>
        <div class="tab-item <?php echo (basename($_SERVER['PHP_SELF']) == 'prize.html') ? 'active' : ''; ?>">
            <a href="/prize.html">
                <div class="tab-icon">🎁</div>
                <div>奖品</div>
            </a>
        </div>
    </div>
    
    <script>
    // 通用函数: 显示消息提示
    function showToast(message, duration = 3000) {
        // 移除可能已存在的toast
        const existingToast = document.querySelector('.toast-message');
        if (existingToast) {
            document.body.removeChild(existingToast);
        }
        
        // 创建新的toast
        const toast = document.createElement('div');
        toast.className = 'toast-message';
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        // 显示动画
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        // 隐藏动画
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                if (document.body.contains(toast)) {
                    document.body.removeChild(toast);
                }
            }, 300);
        }, duration);
    }
    </script>
</body>
</html>
