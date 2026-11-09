-- ============================================================
-- SQLi Lab 初始化脚本（Windows + 小皮面板 / MySQL 5.7）
-- 由 install.php 自动执行；也可以在 phpMyAdmin 里手动导入
-- 注意：会删除旧的 sqli_lab 库
-- ============================================================

SET NAMES utf8mb4;

DROP DATABASE IF EXISTS sqli_lab;
CREATE DATABASE sqli_lab DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE sqli_lab;

-- 登录 / 二阶注入共用
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username   VARCHAR(64)  NOT NULL,
    password   VARCHAR(64)  NOT NULL,
    role       VARCHAR(16)  NOT NULL DEFAULT 'user',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (username, password, role) VALUES
('admin', 'admin888',  'admin'),
('alice', 'alice123',  'user'),
('bob',   'bob@2026',  'user');

-- article.php 的注入点查这张表，固定 3 列
DROP TABLE IF EXISTS articles;
CREATE TABLE articles (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title      VARCHAR(200) NOT NULL,
    content    TEXT         NOT NULL,
    author     VARCHAR(64)  NOT NULL DEFAULT 'admin',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO articles (title, content, author) VALUES
('欢迎来到 SQL 注入实验室',
 '两台实验桌：登录页的万能密码，article.php 的数字型注入点（读文件、写文件），还有一组二阶注入。\n每页顶部都会打印实际执行的 SQL，先看 SQL 再看 payload。',
 'admin'),
('为什么 article.php 有 3 列',
 'UNION 要求前后列数一致。先用 ORDER BY 猜列数，再用 UNION SELECT 1,2,3 找显示位，最后把想读的内容放到显示位上。',
 'admin'),
('二阶注入的坑在哪',
 '第一次入库时用了预处理或转义，看起来很安全；第二次从数据库取出来又直接拼进 SQL，payload 就在这一刻生效。防御要在“每一次拼接”上做，而不是只在入口做。',
 'admin');
