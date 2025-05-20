# Config Database
## Khởi tạo và chạy các container
docker-compose -f docker-compose.yml up -d
docker-compose -f docker-compose.dev.yml up -d

## Xem logs
docker-compose -f docker-compose.yml logs -f
docker-compose -f docker-compose.dev.yml logs -f

## Dừng các container
docker-compose -f docker-compose.yml -f docker-compose.dev.yml down

## Xây dựng lại image sau khi thay đổi Dockerfile
docker-compose -f docker-compose.yml -f docker-compose.dev.yml build

## Chạy sau khi rebuild
docker-compose -f docker-compose.yml -f docker-compose.dev.yml up -d


# Config Laravel
## Khởi tạo database
php artisan session:table
php artisan migrate

