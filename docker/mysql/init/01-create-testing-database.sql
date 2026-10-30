-- 自動テスト用のDB。アプリ用ユーザーに、このDBに限った権限を付与する
CREATE DATABASE IF NOT EXISTS testing;
GRANT ALL PRIVILEGES ON testing.* TO 'app'@'%';
