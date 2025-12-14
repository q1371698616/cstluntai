# 轮胎库存管理系统

基于 PHP+MySQL 开发的轮胎专属库存管理系统，支持小程序 + APP 适配，集商品展示、库存管理、扫码操作、后台管控于一体。

## 系统特点

- ✅ **条形码独立管理**: 每个条形码独立库存，支持精细化追踪
- ✅ **扫码快速操作**: 支持连续扫码，快速入库/出库
- ✅ **三级分类体系**: 车型 → 品牌 → 型号规格
- ✅ **车牌识别**: 出库时支持拍照识别车牌号
- ✅ **数据统计**: 实时库存统计、操作记录查询
- ✅ **响应式设计**: 适配桌面、移动设备、小程序、APP
- ✅ **安全可靠**: JWT认证、密码加密、SQL防注入

## 技术栈

### 后端
- PHP 7.4+
- MySQL 5.7+
- PDO 数据库操作
- JWT 身份认证

### 前端
- HTML5 + CSS3 + JavaScript
- 响应式布局
- 原生 JavaScript (无框架依赖)
- 支持小程序/APP集成

## 项目结构

```
tire-inventory-system/
├── backend/                # 后端代码
│   ├── api/               # API 接口
│   │   ├── auth.php       # 用户认证
│   │   ├── products.php   # 商品管理
│   │   ├── barcodes.php   # 条形码管理
│   │   ├── categories.php # 分类管理
│   │   ├── inventory.php  # 入库/出库
│   │   ├── records.php    # 记录查询
│   │   └── upload.php     # 文件上传
│   ├── config/            # 配置文件
│   │   ├── config.php     # 系统配置
│   │   └── database.php   # 数据库配置
│   ├── core/              # 核心类
│   │   ├── Auth.php       # 认证类
│   │   └── Response.php   # 响应类
│   └── uploads/           # 上传文件目录
│       ├── products/      # 商品图片
│       └── license_plates/# 车牌照片
├── frontend/              # 前端代码
│   ├── index.html         # 主页面
│   ├── styles/            # 样式文件
│   │   └── main.css       # 主样式
│   ├── utils/             # 工具函数
│   │   ├── api.js         # API调用
│   │   └── scanner.js     # 扫码功能
│   └── components/        # 组件
│       └── app.js         # 应用逻辑
├── database/              # 数据库文件
│   ├── schema.sql         # 数据库结构
│   └── sample_data.sql    # 示例数据
└── docs/                  # 文档
    └── DEPLOYMENT.md      # 部署文档
```

## 快速开始

### 1. 环境要求

- PHP 7.4 或更高版本
- MySQL 5.7 或更高版本
- Apache/Nginx Web 服务器
- 支持 PHP PDO 扩展

### 2. 安装步骤

#### 2.1 克隆项目

```bash
git clone <repository-url>
cd tire-inventory-system
```

#### 2.2 导入数据库

```bash
# 创建数据库并导入结构
mysql -u root -p < database/schema.sql

# 导入示例数据（可选）
mysql -u root -p tire_inventory < database/sample_data.sql
```

#### 2.3 配置数据库连接

编辑 `backend/config/database.php`:

```php
private $host = "localhost";
private $db_name = "tire_inventory";
private $username = "root";
private $password = "your_password";
```

#### 2.4 配置 Web 服务器

**Apache 示例配置:**

```apache
<VirtualHost *:80>
    ServerName tire-inventory.local
    DocumentRoot /path/to/tire-inventory-system

    <Directory /path/to/tire-inventory-system>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx 示例配置:**

```nginx
server {
    listen 80;
    server_name tire-inventory.local;
    root /path/to/tire-inventory-system;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /backend/api/ {
        try_files $uri $uri/ /backend/api/index.php?$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

#### 2.5 设置文件权限

```bash
# 设置上传目录权限
chmod -R 755 backend/uploads
chown -R www-data:www-data backend/uploads
```

### 3. 访问系统

打开浏览器访问: `http://tire-inventory.local/frontend/`

**默认账号:**
- 管理员: `admin` / `admin123`
- 普通用户: `user1` / `123456`

**重要**: 首次登录后请立即修改密码！

## 功能模块

### 前台功能

1. **用户认证**
   - 注册/登录
   - 密码修改
   - 权限控制

2. **商品浏览**
   - 分类导航（三级分类）
   - 关键词搜索
   - 商品详情查看

3. **扫码操作** ⭐
   - 条形码扫描
   - 批量入库
   - 批量出库
   - 车牌识别（出库）

4. **记录查询**
   - 入库记录
   - 出库记录
   - 多条件筛选

5. **个人中心**
   - 操作统计
   - 个人信息
   - 密码管理

### 后台功能（管理员）

1. **商品管理**
   - 添加/编辑/删除商品
   - 批量导入
   - 商品分类

2. **条形码管理** ⭐
   - 条形码添加
   - 独立库存管理
   - 存放位置设置
   - 供应商编码

3. **用户管理**
   - 用户列表
   - 状态管控
   - 操作统计

4. **数据大屏**
   - 库存统计
   - 入库/出库趋势
   - 热门商品分析

## API 接口文档

### 认证接口

#### 登录
```http
POST /backend/api/auth.php?action=login
Content-Type: application/json

{
  "username": "admin",
  "password": "admin123"
}
```

#### 注册
```http
POST /backend/api/auth.php?action=register
Content-Type: application/json

{
  "username": "user1",
  "password": "123456",
  "realname": "张三",
  "phone": "13800138000"
}
```

### 商品接口

#### 获取商品列表
```http
GET /backend/api/products.php?page=1&page_size=20&keyword=米其林
Authorization: Bearer {token}
```

#### 获取商品详情
```http
GET /backend/api/products.php?id=1
Authorization: Bearer {token}
```

### 条形码接口

#### 查询条形码
```http
GET /backend/api/barcodes.php?barcode=6901026050101
Authorization: Bearer {token}
```

### 库存操作接口

#### 入库
```http
POST /backend/api/inventory.php?action=inbound
Authorization: Bearer {token}
Content-Type: application/json

{
  "barcode": "6901026050101",
  "quantity": 10
}
```

#### 出库
```http
POST /backend/api/inventory.php?action=outbound
Authorization: Bearer {token}
Content-Type: application/json

{
  "barcode": "6901026050101",
  "quantity": 5,
  "license_plate": "京A12345"
}
```

更多 API 详情请查看各接口文件中的注释。

## 数据库结构

### 核心表

- `users` - 用户表
- `products` - 商品表
- `barcodes` - 条形码表（核心）
- `categories_level1` - 一级分类
- `categories_level2` - 二级分类
- `categories_level3` - 三级分类
- `inbound_records` - 入库记录
- `outbound_records` - 出库记录
- `operation_logs` - 操作日志

### 库存管理规则

- 库存以条形码为单位独立管理
- 同一商品可关联多个条形码
- 每个条形码库存单独计算
- 扫码操作直接关联对应条形码库存变更

## 安全性

- ✅ 密码使用 bcrypt 加密
- ✅ JWT Token 认证
- ✅ SQL 参数化查询，防注入
- ✅ XSS 防护
- ✅ CSRF 防护
- ✅ 文件上传类型验证
- ✅ 支持 HTTPS

## 小程序/APP 集成

### 扫码功能

在微信小程序中:
```javascript
wx.scanCode({
  onlyFromCamera: true,
  scanType: ['barCode'],
  success: (res) => {
    // res.result 是条形码号
  }
});
```

### 车牌识别

调用 OCR 服务或使用摄像头拍照:
```javascript
wx.chooseImage({
  count: 1,
  sourceType: ['camera'],
  success: (res) => {
    // 调用 OCR 识别车牌
  }
});
```

### API 调用

前端需要配置 API 基础地址:
```javascript
const API_BASE_URL = 'https://your-domain.com/backend/api';
```

## 开发调试

### 启用调试模式

编辑 `backend/config/config.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### 查看日志

- PHP 错误日志: `/var/log/php/error.log`
- Apache 日志: `/var/log/apache2/error.log`
- Nginx 日志: `/var/log/nginx/error.log`

## 性能优化

1. 启用 PHP OpCache
2. 使用 MySQL 查询缓存
3. 启用 Gzip 压缩
4. CDN 加速静态资源
5. 数据库索引优化

## 部署到生产环境

详细部署步骤请参考: [部署文档](docs/DEPLOYMENT.md)

主要步骤:
1. 配置 HTTPS
2. 修改 JWT 密钥
3. 关闭调试模式
4. 设置文件权限
5. 配置定时备份

## 常见问题

### 1. 文件上传失败
检查 `backend/uploads` 目录权限是否正确。

### 2. API 跨域问题
已在 `backend/config/config.php` 中配置 CORS。

### 3. Token 验证失败
检查系统时间是否正确，Token 有效期为 7 天。

### 4. 数据库连接失败
检查数据库配置和 MySQL 服务状态。

## 更新日志

### v1.0.0 (2024-12-12)
- ✅ 初始版本发布
- ✅ 完整的前后端功能
- ✅ 条形码独立库存管理
- ✅ 扫码入库/出库
- ✅ 记录查询和统计
- ✅ 用户权限管理

## 开发计划

- [ ] 数据大屏可视化
- [ ] 完善管理后台
- [ ] 导出 Excel 报表
- [ ] 微信小程序版本
- [ ] APP 原生版本
- [ ] 条形码打印功能
- [ ] 库存预警通知
- [ ] 供应商管理

## 技术支持

如有问题，请提交 Issue 或联系开发团队。

## 许可证

MIT License

---

**开发团队**: Claude Code
**最后更新**: 2024-12-12
