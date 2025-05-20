FROM mysql:8

# Copy cấu hình nếu cần (có thể bỏ nếu không dùng)
COPY my.cnf /etc/mysql/conf.d/my.cnf

# Copy script khởi tạo để cấp quyền root từ ngoài container
COPY init.sql /docker-entrypoint-initdb.d/

ENV MYSQL_ROOT_PASSWORD=admin
ENV MYSQL_DATABASE=swinburne
