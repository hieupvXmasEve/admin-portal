-- Initialize test database and users
CREATE DATABASE IF NOT EXISTS swinburne_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create test application user if not exists
CREATE USER IF NOT EXISTS 'swinx_test_user'@'%' IDENTIFIED BY 'swinx_test_password';

-- Grant privileges to test application user
GRANT ALL PRIVILEGES ON swinburne_test.* TO 'swinx_test_user'@'%';

-- Allow root access from any host (testing only)
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY 'test_root_password';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;

-- Flush privileges
FLUSH PRIVILEGES;
