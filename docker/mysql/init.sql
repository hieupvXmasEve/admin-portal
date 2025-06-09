-- Initialize database and users
CREATE DATABASE IF NOT EXISTS swinburne CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create application user if not exists
CREATE USER IF NOT EXISTS 'swinx_user'@'%' IDENTIFIED BY 'swinx_password';

-- Grant privileges to application user
GRANT ALL PRIVILEGES ON swinburne.* TO 'swinx_user'@'%';

-- Allow root access from any host (development only)
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY 'root_password';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;

-- Flush privileges
FLUSH PRIVILEGES;
