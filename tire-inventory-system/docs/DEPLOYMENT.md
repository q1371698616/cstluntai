# 轮胎库存管理系统 - 部署文档

## 系统要求

### 服务器环境
- **操作系统**: Linux / Windows Server
- **Web服务器**: Apache 2.4+ / Nginx 1.18+
- **PHP**: 7.4+ (推荐 8.0+)
- **MySQL**: 5.7+ / 8.0+ (推荐 8.0+)
- **存储空间**: 至少 2GB 可用空间

### PHP 扩展要求
- PDO
- pdo_mysql
- mbstring
- json
- gd (图片处理)
- fileinfo (文件上传)

## 一、数据库安装

### 1. 创建数据库

```bash
# 登录 MySQL
mysql -u root -p

# 或者直接执行 SQL 文件
mysql -u root -p < database/schema.sql
```

### 2. 导入示例数据（可选）

```bash
mysql -u root -p tire_inventory < database/sample_data.sql
```

### 3. 配置数据库连接

编辑 `backend/config/database.php` 文件：

```php
private $host = "localhost";          // 数据库主机
private $db_name = "tire_inventory";  // 数据库名
private $username = "root";           // 数据库用户名
private $password = "your_password";  // 数据库密码
```

## 二、后端部署

### 1. 上传文件

将整个项目上传到服务器 Web 根目录，例如：
- Apache: `/var/www/html/tire-inventory/`
- Nginx: `/usr/share/nginx/html/tire-inventory/`

### 2. 设置文件权限

```bash
# 进入项目目录
cd /var/www/html/tire-inventory-system

# 设置上传目录权限
chmod -R 755 backend/uploads
chown -R www-data:www-data backend/uploads

# 或者使用 777（仅开发环境）
chmod -R 777 backend/uploads
```

### 3. 配置 Web 服务器

#### Apache 配置

创建虚拟主机配置文件 `/etc/apache2/sites-available/tire-inventory.conf`:

```apache
<VirtualHost *:80>
    ServerName tire-inventory.example.com
    DocumentRoot /var/www/html/tire-inventory-system

    <Directory /var/www/html/tire-inventory-system>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # API 重写规则
    <Directory /var/www/html/tire-inventory-system/backend/api>
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [L,QSA]
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/tire-inventory-error.log
    CustomLog ${APACHE_LOG_DIR}/tire-inventory-access.log combined
</VirtualHost>
```

启用站点：
```bash
sudo a2ensite tire-inventory
sudo a2enmod rewrite
sudo systemctl reload apache2
```

#### Nginx 配置

编辑 `/etc/nginx/sites-available/tire-inventory`:

```nginx
server {
    listen 80;
    server_name tire-inventory.example.com;
    root /usr/share/nginx/html/tire-inventory-system;
    index index.html index.php;

    # 设置最大上传大小
    client_max_body_size 10M;

    # 前端静态文件
    location / {
        try_files $uri $uri/ /index.html;
    }

    # 后端 API
    location /backend/api/ {
        try_files $uri $uri/ /backend/api/index.php?$args;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 上传文件访问
    location /backend/uploads/ {
        alias /usr/share/nginx/html/tire-inventory-system/backend/uploads/;
    }
}
```

启用站点：
```bash
sudo ln -s /etc/nginx/sites-available/tire-inventory /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 4. 配置 HTTPS (推荐)

使用 Let's Encrypt 免费证书：

```bash
# 安装 certbot
sudo apt install certbot python3-certbot-apache  # Apache
sudo apt install certbot python3-certbot-nginx   # Nginx

# 获取证书
sudo certbot --apache -d tire-inventory.example.com  # Apache
sudo certbot --nginx -d tire-inventory.example.com   # Nginx
```

### 5. 修改系统配置

编辑 `backend/config/config.php`:

```php
// 生产环境关闭错误显示
error_reporting(0);
ini_set('display_errors', 0);

// 修改 JWT 密钥（必须修改！）
define('JWT_SECRET_KEY', '请修改为随机字符串-至少32位');

// 其他配置...
```

## 三、前端部署

### 1. 配置 API 地址

编辑前端配置文件（根据实际前端框架）:

```javascript
// 示例: config.js
const API_BASE_URL = 'https://tire-inventory.example.com/backend/api';
```

### 2. 构建前端（如需要）

如果使用了构建工具：
```bash
npm install
npm run build
```

### 3. 部署静态文件

将构建后的文件放到 Web 根目录下的 `frontend` 文件夹。

## 四、测试系统

### 1. 测试数据库连接

创建测试文件 `backend/test-db.php`:

```php
<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

if ($conn) {
    echo "数据库连接成功！";
} else {
    echo "数据库连接失败！";
}
?>
```

访问: `http://your-domain/backend/test-db.php`

### 2. 测试 API

使用 Postman 或 curl 测试登录接口：

```bash
curl -X POST http://your-domain/backend/api/auth.php?action=login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

预期返回包含 token 的 JSON 数据。

### 3. 默认账号

**管理员账号**:
- 用户名: `admin`
- 密码: `admin123`

**普通用户**:
- 用户名: `user1`
- 密码: `123456`

**重要**: 首次登录后请立即修改默认密码！

## 五、安全加固

### 1. 文件权限

```bash
# 设置合理的文件权限
find /var/www/html/tire-inventory-system -type f -exec chmod 644 {} \;
find /var/www/html/tire-inventory-system -type d -exec chmod 755 {} \;

# 上传目录需要写权限
chmod -R 755 backend/uploads
```

### 2. 隐藏敏感文件

在 `.htaccess` 中添加（Apache）:

```apache
# 禁止访问配置文件
<FilesMatch "^(config|database)\.php$">
    Require all denied
</FilesMatch>

# 禁止访问 .git 目录
<DirectoryMatch "\.git">
    Require all denied
</DirectoryMatch>
```

或在 Nginx 配置中添加:

```nginx
# 禁止访问配置文件
location ~ ^/backend/config/ {
    deny all;
}

# 禁止访问 .git
location ~ /\.git {
    deny all;
}
```

### 3. 启用防火墙

```bash
# UFW 防火墙（Ubuntu/Debian）
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### 4. 定期备份

创建备份脚本 `backup.sh`:

```bash
#!/bin/bash
BACKUP_DIR="/backup/tire-inventory"
DATE=$(date +%Y%m%d_%H%M%S)

# 备份数据库
mysqldump -u root -p tire_inventory > $BACKUP_DIR/db_$DATE.sql

# 备份上传文件
tar -czf $BACKUP_DIR/uploads_$DATE.tar.gz /var/www/html/tire-inventory-system/backend/uploads

# 删除30天前的备份
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

添加定时任务：
```bash
crontab -e
# 每天凌晨2点执行备份
0 2 * * * /path/to/backup.sh
```

## 六、常见问题

### 1. 文件上传失败

检查 PHP 配置:
```ini
# /etc/php/8.0/apache2/php.ini 或 /etc/php/8.0/fpm/php.ini
upload_max_filesize = 10M
post_max_size = 10M
```

重启服务:
```bash
sudo systemctl restart apache2  # Apache
sudo systemctl restart php8.0-fpm  # PHP-FPM
```

### 2. API 跨域问题

已在 `backend/config/config.php` 中设置了 CORS，如需修改允许的域名：

```php
header("Access-Control-Allow-Origin: https://your-frontend-domain.com");
```

### 3. Token 验证失败

- 检查 JWT_SECRET_KEY 是否一致
- 检查系统时间是否正确
- Token 有效期为 7 天，过期需重新登录

### 4. 数据库连接失败

- 检查数据库服务是否启动
- 检查数据库用户权限
- 检查防火墙设置
- 检查 MySQL bind-address 配置

## 七、性能优化

### 1. 启用 PHP OpCache

编辑 `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

### 2. MySQL 优化

编辑 `/etc/mysql/my.cnf`:
```ini
[mysqld]
innodb_buffer_pool_size = 1G
query_cache_size = 64M
max_connections = 200
```

### 3. 启用 Gzip 压缩

Apache:
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>
```

Nginx:
```nginx
gzip on;
gzip_types text/plain text/css application/json application/javascript text/xml application/xml;
```

## 八、监控和日志

### 1. 错误日志位置

- Apache: `/var/log/apache2/tire-inventory-error.log`
- Nginx: `/var/log/nginx/error.log`
- PHP: `/var/log/php8.0-fpm.log`
- MySQL: `/var/log/mysql/error.log`

### 2. 应用日志

可在 `backend/core/Logger.php` 中实现自定义日志记录。

## 九、维护建议

1. **定期更新**: 保持 PHP、MySQL、Web 服务器为最新稳定版
2. **监控空间**: 定期清理日志和临时文件
3. **安全审计**: 定期检查系统日志和访问日志
4. **数据备份**: 每日备份数据库和上传文件
5. **性能监控**: 使用工具监控系统性能和响应时间

## 联系支持

如遇到问题，请检查：
1. 系统日志
2. 错误提示信息
3. 配置文件是否正确
4. 文件权限是否正确
