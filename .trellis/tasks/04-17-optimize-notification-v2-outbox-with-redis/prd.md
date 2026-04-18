# brainstorm: optimize notification v2 outbox processing with redis

## Goal

Tìm hướng tối ưu xử lý `noti_v2` để giảm delay từ lúc publish event tới lúc người nhận nhận notification, dùng đúng hạ tầng thực tế của repo: Redis đã có sẵn, outbox hiện đang được kéo bằng `notifications:process-outbox`.

## What I already know

* `notifications:process-outbox --limit=100` đang được schedule mỗi phút trong [routes/console.php](/Users/hunt2412/hieupvdev/project/swinx/routes/console.php:67).
* `noti_v2` hiện đi theo pipeline: outbox -> process outbox -> persist message/delivery -> queue channel jobs.
* `SendNotificationDeliveryJob` là queued job cho từng channel delivery.
* Redis queue connection đã được cấu hình trong [config/queue.php](/Users/hunt2412/hieupvdev/project/swinx/config/queue.php:66).
* Default queue hiện vẫn là `env('QUEUE_CONNECTION', 'database')`, không mặc định là Redis.
* Tài liệu hệ thống hiện vẫn xem `notifications:process-outbox` là command baseline cho dispatch.

## Assumptions (temporary)

* Mục tiêu chính là giảm latency, không phải redesign toàn bộ notification domain.
* Có thể thay đổi command/scheduler/job wiring nếu lợi ích rõ ràng.
* Redis là hạ tầng production-ready trong môi trường deploy hiện tại.

## Open Questions

* Nên giữ polling outbox bằng scheduler, hay chuyển sang queue-first dispatch ngay sau commit?

## Requirements (evolving)

* Đề xuất 2-3 hướng tối ưu thực tế cho outbox processing của `noti_v2`.
* Ưu tiên giải pháp tận dụng Redis hiện có.
* Phải chỉ ra trade-off giữa độ trễ, độ phức tạp vận hành, và độ an toàn.

## Acceptance Criteria (evolving)

* [ ] Xác định rõ nút thắt hiện tại của `notifications:process-outbox`.
* [ ] Có phương án khuyến nghị chính, phù hợp với kiến trúc hiện tại.
* [ ] Chỉ ra phương án nào nên tránh dù technically possible.

## Definition of Done (team quality bar)

* Kết luận bám đúng code hiện tại
* Không giả định sai về queue / Redis / outbox flow
* Có nêu rollout path nếu user muốn implement

## Out of Scope (explicit)

* Chưa sửa code
* Chưa bàn UI / template content
* Chưa redesign toàn bộ notification v2 schema

## Technical Notes

* Inspected: [routes/console.php](/Users/hunt2412/hieupvdev/project/swinx/routes/console.php:67), [config/queue.php](/Users/hunt2412/hieupvdev/project/swinx/config/queue.php:7), [config/notification.php](/Users/hunt2412/hieupvdev/project/swinx/config/notification.php:3)
* Inspected: [app/Modules/Notification/Console/ProcessNotificationOutboxCommand.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Notification/Console/ProcessNotificationOutboxCommand.php:7), [app/Modules/Notification/Actions/DispatchOutboxBatchAction.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Notification/Actions/DispatchOutboxBatchAction.php:12), [app/Modules/Notification/Jobs/ProcessNotificationOutboxJob.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Notification/Jobs/ProcessNotificationOutboxJob.php:12), [app/Modules/Notification/Jobs/SendNotificationDeliveryJob.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Notification/Jobs/SendNotificationDeliveryJob.php:14)
* Docs confirm scheduler-driven model today: [docs/features/notification/notification-realtime-architecture.md](/Users/hunt2412/hieupvdev/project/swinx/docs/features/notification/notification-realtime-architecture.md:1)
