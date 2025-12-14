-- 轮胎库存管理系统数据库结构
-- 版本: 1.0.0
-- 创建日期: 2025-12-12

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 创建数据库
-- ----------------------------
CREATE DATABASE IF NOT EXISTS `tire_inventory` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tire_inventory`;

-- ----------------------------
-- 1. 用户表
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `password` varchar(255) NOT NULL COMMENT '密码（加密）',
  `realname` varchar(50) DEFAULT NULL COMMENT '真实姓名',
  `phone` varchar(20) DEFAULT NULL COMMENT '手机号',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `role` enum('admin','user') DEFAULT 'user' COMMENT '角色：admin-管理员，user-普通用户',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态：1-启用，0-禁用',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_username` (`username`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- ----------------------------
-- 2. 一级分类表（车型分类）
-- ----------------------------
DROP TABLE IF EXISTS `categories_level1`;
CREATE TABLE `categories_level1` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '分类ID',
  `name` varchar(50) NOT NULL COMMENT '分类名称',
  `sort_order` int(11) DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态：1-启用，0-禁用',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='一级分类表';

-- ----------------------------
-- 3. 二级分类表（品牌分类）
-- ----------------------------
DROP TABLE IF EXISTS `categories_level2`;
CREATE TABLE `categories_level2` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '分类ID',
  `parent_id` int(11) NOT NULL COMMENT '一级分类ID',
  `name` varchar(50) NOT NULL COMMENT '品牌名称',
  `sort_order` int(11) DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态：1-启用，0-禁用',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='二级分类表';

-- ----------------------------
-- 4. 三级分类表（型号规格）
-- ----------------------------
DROP TABLE IF EXISTS `categories_level3`;
CREATE TABLE `categories_level3` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '分类ID',
  `parent_id` int(11) NOT NULL COMMENT '二级分类ID',
  `name` varchar(100) NOT NULL COMMENT '型号规格',
  `sort_order` int(11) DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态：1-启用，0-禁用',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='三级分类表';

-- ----------------------------
-- 5. 商品表
-- ----------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '商品ID',
  `name` varchar(200) NOT NULL COMMENT '商品名称',
  `model` varchar(100) NOT NULL COMMENT '型号',
  `category1_id` int(11) NOT NULL COMMENT '一级分类ID',
  `category2_id` int(11) NOT NULL COMMENT '二级分类ID',
  `category3_id` int(11) NOT NULL COMMENT '三级分类ID',
  `price` decimal(10,2) DEFAULT 0.00 COMMENT '价格',
  `image` varchar(255) DEFAULT NULL COMMENT '商品图片',
  `description` text COMMENT '商品详情',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态：1-上架，0-下架',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_category1` (`category1_id`),
  KEY `idx_category2` (`category2_id`),
  KEY `idx_category3` (`category3_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品表';

-- ----------------------------
-- 6. 条形码表（核心表）
-- ----------------------------
DROP TABLE IF EXISTS `barcodes`;
CREATE TABLE `barcodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '条形码ID',
  `barcode` varchar(100) NOT NULL COMMENT '条形码号',
  `product_id` int(11) NOT NULL COMMENT '关联商品ID',
  `stock` int(11) DEFAULT 0 COMMENT '当前库存',
  `location` varchar(100) DEFAULT NULL COMMENT '存放位置（如A区1号货架）',
  `supplier_code` varchar(100) DEFAULT NULL COMMENT '供应商编码',
  `remark` text COMMENT '备注',
  `last_inbound_time` datetime DEFAULT NULL COMMENT '最后入库时间',
  `last_outbound_time` datetime DEFAULT NULL COMMENT '最后出库时间',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_barcode` (`barcode`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='条形码表';

-- ----------------------------
-- 7. 入库记录表
-- ----------------------------
DROP TABLE IF EXISTS `inbound_records`;
CREATE TABLE `inbound_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `barcode_id` int(11) NOT NULL COMMENT '条形码ID',
  `barcode` varchar(100) NOT NULL COMMENT '条形码号',
  `product_id` int(11) NOT NULL COMMENT '商品ID',
  `quantity` int(11) NOT NULL COMMENT '入库数量',
  `operator_id` int(11) NOT NULL COMMENT '操作人ID',
  `operator_name` varchar(50) DEFAULT NULL COMMENT '操作人姓名',
  `inbound_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '入库时间',
  `remark` text COMMENT '备注',
  PRIMARY KEY (`id`),
  KEY `idx_barcode` (`barcode`),
  KEY `idx_product` (`product_id`),
  KEY `idx_operator` (`operator_id`),
  KEY `idx_time` (`inbound_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='入库记录表';

-- ----------------------------
-- 8. 出库记录表
-- ----------------------------
DROP TABLE IF EXISTS `outbound_records`;
CREATE TABLE `outbound_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `barcode_id` int(11) NOT NULL COMMENT '条形码ID',
  `barcode` varchar(100) NOT NULL COMMENT '条形码号',
  `product_id` int(11) NOT NULL COMMENT '商品ID',
  `quantity` int(11) NOT NULL COMMENT '出库数量',
  `license_plate` varchar(20) DEFAULT NULL COMMENT '车牌号',
  `license_plate_image` varchar(255) DEFAULT NULL COMMENT '车牌照片',
  `operator_id` int(11) NOT NULL COMMENT '操作人ID',
  `operator_name` varchar(50) DEFAULT NULL COMMENT '操作人姓名',
  `outbound_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '出库时间',
  `remark` text COMMENT '备注',
  PRIMARY KEY (`id`),
  KEY `idx_barcode` (`barcode`),
  KEY `idx_product` (`product_id`),
  KEY `idx_license_plate` (`license_plate`),
  KEY `idx_operator` (`operator_id`),
  KEY `idx_time` (`outbound_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='出库记录表';

-- ----------------------------
-- 9. 操作日志表
-- ----------------------------
DROP TABLE IF EXISTS `operation_logs`;
CREATE TABLE `operation_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `user_id` int(11) DEFAULT NULL COMMENT '用户ID',
  `username` varchar(50) DEFAULT NULL COMMENT '用户名',
  `action` varchar(100) NOT NULL COMMENT '操作动作',
  `module` varchar(50) DEFAULT NULL COMMENT '操作模块',
  `ip_address` varchar(50) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` varchar(255) DEFAULT NULL COMMENT '用户代理',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志表';

SET FOREIGN_KEY_CHECKS = 1;
