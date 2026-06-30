# API tiếp nhận hồ sơ từ CRM tuyển sinh

Cập nhật lần cuối: 2026-07-01

## Tổng quan

Tài liệu này mô tả cách CRM tuyển sinh bên ngoài tích hợp với Swinx để tạo và cập nhật hồ sơ tuyển sinh của sinh viên.

Đây là API server-to-server. API này không dành cho ứng viên, sinh viên, giảng viên hoặc giao diện web dành cho nhân viên Swinx.

CRM có thể:

- Kiểm tra kết nối tiếp nhận dữ liệu có hoạt động hay không.
- Tạo một hồ sơ tuyển sinh mới trong Swinx.
- Cập nhật một hồ sơ tuyển sinh hiện có khi hồ sơ vẫn ở trạng thái `pending`.
- Gửi thông tin ứng viên, nguyện vọng nhập học, một kết quả kiểm tra tiếng Anh, thông tin Người giám hộ và đường dẫn tài liệu bên ngoài.

CRM không thể:

- Duyệt hồ sơ tuyển sinh.
- Từ chối hồ sơ tuyển sinh.
- Thu hồi một hồ sơ đã được duyệt.
- Tải trực tiếp file nhị phân lên Swinx.
- Sửa hồ sơ sau khi nhân viên Swinx đã ghi danh hoặc từ chối hồ sơ đó.

## URL cơ sở

Swinx sẽ cung cấp URL cơ sở theo từng môi trường.

```text
<SWINX_BASE_URL>/api/v1/admissions
```

Ví dụ URL theo môi trường chính thức:

```text
GET  <SWINX_BASE_URL>/api/v1/admissions/ping
POST <SWINX_BASE_URL>/api/v1/admissions/applications
```

## Xác thực

Swinx sẽ cấp token tiếp nhận dữ liệu cho CRM.

Gửi token dưới dạng Bearer token trong mọi yêu cầu:

```http
Authorization: Bearer <ADMISSIONS_INGEST_TOKEN>
Accept: application/json
Content-Type: application/json
```

Ở môi trường chính thức, Swinx có thể giới hạn truy cập theo danh sách IP nguồn đi ra đã thống nhất với CRM. Nếu CRM gửi yêu cầu từ IP chưa được cho phép, Swinx trả về `403 Forbidden`.

Yêu cầu xử lý token:

- Lưu token trong hệ thống quản lý bí mật.
- Không commit token vào hệ thống quản lý mã nguồn.
- Không để token xuất hiện trong trình duyệt hoặc ứng dụng di động.
- Yêu cầu nhân sự vận hành Swinx xoay vòng token nếu token bị lộ hoặc không còn cần sử dụng.

## Cấu trúc phản hồi

Mọi phản hồi thành công dùng cấu trúc sau:

```json
{
    "success": true,
    "timestamp": "2026-07-01T00:00:00.000000Z",
    "data": {},
    "message": "..."
}
```

Mọi phản hồi lỗi dùng cấu trúc sau:

```json
{
    "success": false,
    "message": "...",
    "errors": [
        {
            "code": "VALIDATION_ERROR",
            "field": "field_name",
            "detail": "Chi tiết lỗi dạng người đọc được."
        }
    ],
    "timestamp": "2026-07-01T00:00:00.000000Z"
}
```

## Tóm tắt API

| Phương thức | Đường dẫn       | Mục đích                                                    |
| ----------- | --------------- | ----------------------------------------------------------- |
| `GET`       | `/ping`         | Kiểm tra token, danh sách IP cho phép và giới hạn tần suất. |
| `POST`      | `/applications` | Tạo hoặc cập nhật hồ sơ tuyển sinh đang `pending`.          |

## GET `/ping`

Dùng API này để xác nhận CRM có thể kết nối tới Swinx và token tiếp nhận dữ liệu còn hợp lệ.

### Yêu cầu

```bash
curl -X GET "<SWINX_BASE_URL>/api/v1/admissions/ping" \
  -H "Authorization: Bearer <ADMISSIONS_INGEST_TOKEN>" \
  -H "Accept: application/json"
```

### Phản hồi thành công

HTTP `200 OK`

```json
{
    "success": true,
    "timestamp": "2026-07-01T00:00:00.000000Z",
    "data": {
        "service": "admissions-ingestion",
        "ability": "admissions:ingest"
    },
    "message": "pong"
}
```

## POST `/applications`

Tạo hoặc cập nhật một hồ sơ tuyển sinh trong Swinx.

Đây là API tạo-hoặc-cập-nhật:

- Nếu `crm_admission_id` chưa tồn tại trong Swinx, Swinx tạo hồ sơ tuyển sinh mới và trả về `201 Created`.
- Nếu `crm_admission_id` đã tồn tại và hồ sơ vẫn ở trạng thái `pending`, Swinx cập nhật hồ sơ và trả về `200 OK`.
- Nếu `crm_admission_id` đã tồn tại nhưng hồ sơ ở trạng thái `enrolled` hoặc `rejected`, Swinx từ chối cập nhật và trả về `409 Conflict`.

### Quy tắc tạo-hoặc-cập-nhật quan trọng

- `crm_admission_id` là khóa chống tạo trùng của hồ sơ tuyển sinh. CRM phải dùng lại cùng một giá trị khi gửi lại hoặc gửi dữ liệu chỉnh sửa cho cùng một hồ sơ tuyển sinh.
- `crm_file_id` là khóa chống tạo trùng của tài liệu. CRM nên dùng một file id ổn định và duy nhất toàn cục cho từng file trong CRM.
- Các trường bắt buộc phải được gửi trong mọi yêu cầu `POST /applications`, bao gồm cả yêu cầu cập nhật.
- API này không phải API cập nhật một phần kiểu PATCH.
- Các trường đơn tùy chọn chỉ được cập nhật khi CRM gửi trường đó.
- `english_test` được thay thế theo cả nhóm. Nếu `english_test` bị bỏ qua hoặc gửi `null` khi cập nhật, các giá trị kiểm tra tiếng Anh hiện có sẽ bị xóa về null.
- `guardians` được thay thế theo cả nhóm khi được gửi.
- `documents` được upsert theo `crm_file_id` khi được gửi.

### Yêu cầu

```bash
curl -X POST "<SWINX_BASE_URL>/api/v1/admissions/applications" \
  -H "Authorization: Bearer <ADMISSIONS_INGEST_TOKEN>" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d @payload.json
```

## Nội dung yêu cầu

### Giá trị được phép cho các field mã

CRM gửi **code**, không gửi `id` số hoặc tên hiển thị.

#### `campus_code`

| Giá trị được phép | Ý nghĩa          |
| ----------------- | ---------------- |
| `HCM`             | Asia Hồ Chí Minh |
| `HN`              | Asia Hà Nội      |

#### `intended_program`

| Giá trị được phép | Ý nghĩa                   |
| ----------------- | ------------------------- |
| `AI`              | Artificial Intelligence   |
| `BA`              | Business Administration   |
| `FIN`             | Finance                   |
| `SEMI`            | Semiconductor Engineering |

#### `intake`

| Giá trị được phép |
| ----------------- |
| `FALL2025`        |
| `SPRING2026`      |
| `SUMMER2026`      |
| `FALL2026`        |
| `SPRING2027`      |
| `SUMMER2027`      |
| `FALL2027`        |

#### `intended_specialization`

Hiện chưa có giá trị specialization nào được phép. CRM nên bỏ qua field này hoặc gửi `null`.

#### Curriculum version

CRM không gửi `curriculum_version`, `curriculum_version_code` hoặc `curriculum_version_id`.

Swinx tự xác định curriculum version khi nhân viên duyệt hồ sơ. Các cặp dưới đây hiện duyệt được ngay:

| `intended_program` | `intake`   | Curriculum version |
| ------------------ | ---------- | ------------------ |
| `AI`               | `FALL2025` | `AI2025.v1`        |
| `BA`               | `FALL2025` | `BA2025.v1`        |
| `FIN`              | `FALL2025` | `FI2025.v1`        |
| `SEMI`             | `FALL2025` | `SE2025.v1`        |

Các giá trị `intake` khác trong bảng trên vẫn hợp lệ với API, nhưng có thể cần Swinx cấu hình curriculum version trước khi hồ sơ được duyệt.

#### `documents[].file_type_code`

| Giá trị được phép      | Ý nghĩa                                                                                               | Bắt buộc cho hồ sơ? |
| ---------------------- | ----------------------------------------------------------------------------------------------------- | ------------------- |
| `id_card_front`        | CCCD (mặt trước)                                                                                      | Có                  |
| `id_card_back`         | CCCD (mặt sau)                                                                                        | Có                  |
| `transcript`           | Bảng điểm/học bạ                                                                                      | Có                  |
| `english_certificate`  | Chứng chỉ tiếng Anh                                                                                   | Không               |
| `other_achievements`   | Thành tích khác                                                                                       | Không               |
| `student_photo`        | Ảnh học sinh                                                                                          | Không               |
| `diploma`              | Bằng tốt nghiệp THPT hoặc Giấy chứng nhận tốt nghiệp tạm thời                                         | Có                  |
| `transcript_1`         | Học bạ THPT                                                                                           | Có                  |
| `english_certificare`  | Chứng chỉ tiếng Anh quốc tế còn hiệu lực, nếu có                                                      | Không               |
| `birth_certificate`    | Giấy khai sinh                                                                                        | Không               |
| `other_achievements_1` | Giấy xác nhận sinh viên của anh/chị/em ruột đang theo học tại các chương trình đào tạo của FE, nếu có | Không               |
| `other_achievements_2` | Giấy tờ chứng nhận thành tích khác, nếu có                                                            | Không               |

`english_certificare` là code được phép hiện tại, bao gồm lỗi chính tả trong code. CRM phải gửi đúng giá trị này nếu muốn dùng loại tài liệu đó.

### Payload tối thiểu hợp lệ

```json
{
    "crm_admission_id": "743",
    "student_code": "AUS10743",
    "full_name": "Nguyen Van A",
    "campus_code": "HCM",
    "intended_program": "AI",
    "intake": "FALL2025"
}
```

### Ví dụ payload đầy đủ

```json
{
    "crm_admission_id": "743",
    "student_code": "AUS10743",
    "full_name": "Nguyen Van A",
    "gender": "male",
    "ethnicity": "Kinh",
    "birth_day": 1,
    "birth_month": 2,
    "birth_year": 2003,
    "national_id": "0123456789",
    "phone": "0900000000",
    "email": "applicant@example.test",
    "address": "Ho Chi Minh City",
    "health_information": "No known issues",
    "campus_code": "HCM",
    "intended_program": "AI",
    "intended_specialization": null,
    "intake": "FALL2025",
    "is_international_applicant": false,
    "exception_units": null,
    "sut_id": "SUT-123",
    "english_qualifications": "IELTS",
    "study_link_status": "ready",
    "english_test": {
        "test_type": "IELTS",
        "exam_date": "2025-01-15",
        "listening": 7,
        "reading": 6.5,
        "writing": 6,
        "speaking": 6.5,
        "overall": 6.5
    },
    "guardians": [
        {
            "full_name": "Tran Thi B",
            "relationship": "mother",
            "phone": "0911111111",
            "email": "mother@example.test",
            "occupation": "Accountant",
            "address": "Ho Chi Minh City",
            "is_primary": true
        }
    ],
    "documents": [
        {
            "crm_file_id": "190",
            "file_type_code": "id_card_front",
            "file_type_name": "CCCD (mặt trước)",
            "page_index": 0,
            "original_name": "IMG_0923.jpeg",
            "link": "https://drive.google.com/file/d/19-kY2rN3rKXsUAq2lraoUKlUJhrHF8j7/preview",
            "mime_type": "image/jpeg",
            "size": 362636,
            "status": "active"
        }
    ]
}
```

## Tham chiếu trường dữ liệu

### Trường của hồ sơ tuyển sinh

| Trường                       | Kiểu dữ liệu      | Bắt buộc | Mô tả                                                                                               |
| ---------------------------- | ----------------- | -------- | --------------------------------------------------------------------------------------------------- |
| `crm_admission_id`           | string            | Có       | ID hồ sơ tuyển sinh ổn định từ CRM. Dùng làm khóa chống tạo trùng của hồ sơ tuyển sinh. Tối đa 255. |
| `student_code`               | string hoặc null  | Không    | Mã sinh viên do CRM cấp. Tối đa 255.                                                                |
| `full_name`                  | string            | Có       | Họ tên đầy đủ của ứng viên. Tối đa 255.                                                             |
| `gender`                     | string hoặc null  | Không    | Giá trị giới tính từ CRM. Tối đa 20.                                                                |
| `ethnicity`                  | string hoặc null  | Không    | Dân tộc của ứng viên. Tối đa 100.                                                                   |
| `birth_day`                  | integer hoặc null | Không    | Ngày sinh, từ 1 đến 31.                                                                             |
| `birth_month`                | integer hoặc null | Không    | Tháng sinh, từ 1 đến 12.                                                                            |
| `birth_year`                 | integer hoặc null | Không    | Năm sinh, từ 1900 đến năm hiện tại.                                                                 |
| `national_id`                | string hoặc null  | Không    | Số định danh quốc gia hoặc giấy tờ tương đương passport. Tối đa 50.                                 |
| `phone`                      | string hoặc null  | Không    | Số điện thoại của ứng viên. Tối đa 50.                                                              |
| `email`                      | string hoặc null  | Không    | Email của ứng viên. Phải đúng định dạng email. Tối đa 255.                                          |
| `address`                    | string hoặc null  | Không    | Địa chỉ của ứng viên.                                                                               |
| `health_information`         | string hoặc null  | Không    | Ghi chú sức khỏe của ứng viên.                                                                      |
| `campus_code`                | string            | Có       | Phải là một `campuses.code` hiện có. Xem bảng mã campus ở trên. Tối đa 50.                          |
| `intended_program`           | string            | Có       | Phải là một `programs.code` hiện có. Xem bảng mã chương trình/ngành ở trên. Tối đa 255.             |
| `intended_specialization`    | string hoặc null  | Không    | Hiện chưa có giá trị specialization nào được phép. Bỏ qua field này hoặc gửi `null`. Tối đa 255.    |
| `intake`                     | string            | Có       | Phải là một `semesters.code` hiện có. Xem bảng mã kỳ nhập học ở trên. Tối đa 100.                   |
| `is_international_applicant` | boolean hoặc null | Không    | Ứng viên có phải ứng viên quốc tế hay không.                                                        |
| `exception_units`            | string hoặc null  | Không    | Ghi chú hoặc mã exception unit từ CRM.                                                              |
| `sut_id`                     | string hoặc null  | Không    | Mã SUT từ CRM, nếu có. Tối đa 100.                                                                  |
| `english_qualifications`     | string hoặc null  | Không    | Ghi chú về chứng chỉ/năng lực tiếng Anh từ CRM.                                                     |
| `study_link_status`          | string hoặc null  | Không    | Trạng thái study-link từ CRM. Tối đa 100.                                                           |

### Trường của kết quả kiểm tra tiếng Anh

`english_test` là tùy chọn. Nếu gửi, giá trị phải là object JSON.

| Trường      | Kiểu dữ liệu     | Bắt buộc | Mô tả                                        |
| ----------- | ---------------- | -------- | -------------------------------------------- |
| `test_type` | string hoặc null | Không    | Loại bài kiểm tra, ví dụ `IELTS`. Tối đa 50. |
| `exam_date` | date hoặc null   | Không    | Định dạng khuyến nghị: `YYYY-MM-DD`.         |
| `listening` | number hoặc null | Không    | Điểm từ 0 đến 99.99.                         |
| `reading`   | number hoặc null | Không    | Điểm từ 0 đến 99.99.                         |
| `writing`   | number hoặc null | Không    | Điểm từ 0 đến 99.99.                         |
| `speaking`  | number hoặc null | Không    | Điểm từ 0 đến 99.99.                         |
| `overall`   | number hoặc null | Không    | Điểm từ 0 đến 99.99.                         |

Ví dụ:

```json
{
    "english_test": {
        "test_type": "IELTS",
        "exam_date": "2025-01-15",
        "listening": 7,
        "reading": 6.5,
        "writing": 6,
        "speaking": 6.5,
        "overall": 6.5
    }
}
```

### Trường của Người giám hộ

`guardians` là tùy chọn.

Nếu bỏ qua, danh sách Người giám hộ hiện có không thay đổi.

Nếu gửi dạng mảng, Swinx thay thế danh sách Người giám hộ hiện có bằng danh sách được gửi.

Nếu gửi `[]` hoặc `null`, Swinx xóa toàn bộ Người giám hộ hiện có khỏi hồ sơ tuyển sinh.

| Trường         | Kiểu dữ liệu      | Bắt buộc | Mô tả                                                           |
| -------------- | ----------------- | -------- | --------------------------------------------------------------- |
| `full_name`    | string            | Có       | Họ tên đầy đủ của Người giám hộ. Tối đa 255.                    |
| `relationship` | string hoặc null  | Không    | Phải là một trong các giá trị relationship được cho phép.       |
| `phone`        | string hoặc null  | Không    | Số điện thoại của Người giám hộ. Tối đa 50.                     |
| `email`        | string hoặc null  | Không    | Email của Người giám hộ. Phải đúng định dạng email. Tối đa 255. |
| `occupation`   | string hoặc null  | Không    | Nghề nghiệp của Người giám hộ. Tối đa 255.                      |
| `address`      | string hoặc null  | Không    | Địa chỉ của Người giám hộ. Tối đa 255.                          |
| `is_primary`   | boolean hoặc null | Không    | Đánh dấu Người giám hộ chính.                                   |

Các giá trị `relationship` được phép:

```text
father
mother
guardian
grandfather
grandmother
sibling
uncle
aunt
other
```

Quy tắc Người giám hộ chính:

- Nếu danh sách có Người giám hộ, Swinx luôn giữ đúng một Người giám hộ chính.
- Nếu không có Người giám hộ nào được đánh dấu `is_primary: true`, Người giám hộ đầu tiên sẽ trở thành Người giám hộ chính.
- Nếu nhiều Người giám hộ được đánh dấu `is_primary: true`, Swinx giữ một Người giám hộ chính theo thứ tự nhận dữ liệu.

Ví dụ:

```json
{
    "guardians": [
        {
            "full_name": "Tran Thi B",
            "relationship": "mother",
            "phone": "0911111111",
            "email": "mother@example.test",
            "is_primary": true
        },
        {
            "full_name": "Nguyen Van C",
            "relationship": "father",
            "phone": "0922222222",
            "email": "father@example.test",
            "is_primary": false
        }
    ]
}
```

### Trường của tài liệu

`documents` là tùy chọn.

Nếu bỏ qua, danh sách tài liệu hiện có không thay đổi.

Nếu gửi dạng mảng, từng tài liệu sẽ được tạo hoặc cập nhật theo `crm_file_id`.

Nếu gửi `[]` hoặc `null`, Swinx không xóa tài liệu hiện có. API này không hỗ trợ xóa tài liệu.

| Trường           | Kiểu dữ liệu      | Bắt buộc | Mô tả                                                                                   |
| ---------------- | ----------------- | -------- | --------------------------------------------------------------------------------------- |
| `crm_file_id`    | string            | Có       | ID file ổn định từ CRM. Dùng làm khóa chống tạo trùng của tài liệu. Tối đa 255.         |
| `file_type_code` | string            | Có       | Gửi một trong các giá trị được liệt kê ở phần `documents[].file_type_code`. Tối đa 255. |
| `file_type_name` | string hoặc null  | Không    | Tên loại tài liệu dạng người đọc được. Tối đa 255.                                      |
| `page_index`     | integer hoặc null | Không    | Thứ tự trang cho file nhiều trang. Nhỏ nhất là 0. Mặc định là 0.                        |
| `original_name`  | string hoặc null  | Không    | Tên file gốc. Tối đa 255.                                                               |
| `link`           | string            | Có       | URL hoặc tham chiếu bên ngoài mà nhân viên Swinx có thể truy cập. Tối đa 2048.          |
| `mime_type`      | string hoặc null  | Không    | MIME type, ví dụ `image/jpeg`. Tối đa 100.                                              |
| `size`           | integer hoặc null | Không    | Kích thước file theo byte, nếu có. Nhỏ nhất là 0.                                       |
| `status`         | string hoặc null  | Không    | Trạng thái file từ CRM, ví dụ `active`. Tối đa 50.                                      |

Ghi chú về tài liệu:

- Swinx chỉ lưu tham chiếu tới tài liệu.
- Swinx không tải lên, sao chép hoặc tải xuống file nhị phân từ API này.
- Nhiều tài liệu có thể dùng cùng một `file_type_code`.
- CRM cần đảm bảo `link` vẫn truy cập được bởi nhân viên Swinx theo chính sách phân quyền đã thống nhất.
- `file_type_name` là tùy chọn; nếu bỏ qua và `file_type_code` là giá trị được phép, Swinx tự dùng tên tài liệu tương ứng.
- CRM chỉ nên gửi các `file_type_code` được liệt kê. Code khác có thể được lưu nhưng không được xem là tài liệu chuẩn khi kiểm tra hồ sơ thiếu tài liệu.

Ví dụ:

```json
{
    "documents": [
        {
            "crm_file_id": "190",
            "file_type_code": "id_card_front",
            "file_type_name": "CCCD (mặt trước)",
            "page_index": 0,
            "original_name": "IMG_0923.jpeg",
            "link": "https://drive.google.com/file/d/19-kY2rN3rKXsUAq2lraoUKlUJhrHF8j7/preview",
            "mime_type": "image/jpeg",
            "size": 362636,
            "status": "active"
        }
    ]
}
```

## Các trường do Swinx quản lý

Không gửi các trường sau trong payload:

```text
status
student_id
approved_by
approved_at
rejected_by
rejected_at
rejected_reason
revoked_by
revoked_at
curriculum_version
curriculum_version_code
curriculum_version_id
```

Swinx tự quản lý các trường này. Nếu CRM gửi các trường này trong yêu cầu, Swinx sẽ bỏ qua.

## Phản hồi thành công

### Tạo mới

HTTP `201 Created`

```json
{
    "success": true,
    "timestamp": "2026-07-01T00:00:00.000000Z",
    "data": {
        "id": 101,
        "crm_admission_id": "743",
        "student_code": "AUS10743",
        "status": "pending",
        "guardians": [
            {
                "id": 501,
                "full_name": "Tran Thi B",
                "is_primary": true
            }
        ],
        "documents": [
            {
                "id": 701,
                "crm_file_id": "190",
                "file_type_code": "id_card_front"
            }
        ]
    },
    "message": "Application created."
}
```

### Cập nhật

HTTP `200 OK`

```json
{
    "success": true,
    "timestamp": "2026-07-01T00:00:00.000000Z",
    "data": {
        "id": 101,
        "crm_admission_id": "743",
        "student_code": "AUS10743",
        "status": "pending",
        "guardians": [
            {
                "id": 502,
                "full_name": "Tran Thi B",
                "is_primary": true
            }
        ],
        "documents": [
            {
                "id": 701,
                "crm_file_id": "190",
                "file_type_code": "id_card_front"
            }
        ]
    },
    "message": "Application updated."
}
```

## Phản hồi lỗi

### `401 Unauthorized`

Token bị thiếu, không hợp lệ hoặc đã hết hạn.

```json
{
    "success": false,
    "message": "Unauthenticated.",
    "errors": [
        {
            "code": "AUTHENTICATION_ERROR",
            "field": null,
            "detail": null
        }
    ],
    "timestamp": "2026-07-01T00:00:00.000000Z"
}
```

### `403 Forbidden`

Token không được phép gọi API tiếp nhận hồ sơ tuyển sinh, hoặc yêu cầu đến từ IP chưa được cho phép.

```json
{
    "success": false,
    "message": "This token lacks the admissions ingestion ability.",
    "errors": [
        {
            "code": "AUTHORIZATION_ERROR",
            "field": null,
            "detail": null
        }
    ],
    "timestamp": "2026-07-01T00:00:00.000000Z"
}
```

Thông báo `403` thay thế khi lỗi danh sách IP cho phép:

```json
{
    "success": false,
    "message": "Source IP is not allowed for admissions ingestion.",
    "errors": [
        {
            "code": "AUTHORIZATION_ERROR",
            "field": null,
            "detail": null
        }
    ],
    "timestamp": "2026-07-01T00:00:00.000000Z"
}
```

### `409 Conflict`

Hồ sơ tuyển sinh đã tồn tại nhưng không còn được phép chỉnh sửa qua API tiếp nhận dữ liệu từ CRM.

Trường hợp này xảy ra sau khi hồ sơ chuyển sang `enrolled` hoặc `rejected`.

```json
{
    "success": false,
    "message": "This application is frozen at approval and can no longer be updated via ingestion.",
    "errors": [
        {
            "code": "CONFLICT",
            "field": null,
            "detail": null
        }
    ],
    "timestamp": "2026-07-01T00:00:00.000000Z"
}
```

Khuyến nghị xử lý phía CRM:

- Dừng gửi lại tự động cho hồ sơ tuyển sinh này.
- Hiển thị xung đột cho người vận hành CRM.
- Phối hợp chỉnh sửa với nhân viên Swinx ngoài API tiếp nhận dữ liệu.

### `422 Unprocessable Entity`

Nội dung yêu cầu là JSON hợp lệ nhưng dữ liệu trường không đạt kiểm tra hợp lệ.

```json
{
    "success": false,
    "message": "The given data was invalid",
    "errors": [
        {
            "code": "VALIDATION_ERROR",
            "field": "crm_admission_id",
            "detail": "The crm admission id field is required."
        },
        {
            "code": "VALIDATION_ERROR",
            "field": "campus_code",
            "detail": "The selected campus code is invalid."
        }
    ],
    "timestamp": "2026-07-01T00:00:00.000000Z"
}
```

Khuyến nghị xử lý phía CRM:

- Không gửi lại mù cùng payload.
- Sửa các trường không hợp lệ.
- Gửi lại payload đã chỉnh sửa với cùng `crm_admission_id`.

### `429 Too Many Requests`

CRM đã vượt quá giới hạn yêu cầu được phép.

```json
{
    "success": false,
    "message": "Too many requests. Try again in 60 seconds.",
    "errors": [
        {
            "code": "RATE_LIMIT",
            "field": null,
            "detail": null
        }
    ],
    "timestamp": "2026-07-01T00:00:00.000000Z"
}
```

Khuyến nghị xử lý phía CRM:

- Chờ trước khi gửi lại.
- Nên dùng backoff tăng dần kèm jitter khi gửi theo batch.

## Giá trị trạng thái hồ sơ

| Trạng thái | Ý nghĩa                                        | CRM có thể cập nhật? |
| ---------- | ---------------------------------------------- | -------------------- |
| `pending`  | Hồ sơ đang chờ nhân viên Swinx xem xét.        | Có                   |
| `enrolled` | Hồ sơ đã được duyệt và liên kết với sinh viên. | Không                |
| `rejected` | Hồ sơ đã bị nhân viên Swinx từ chối.           | Không                |

Chỉ quy trình của nhân viên Swinx mới thay đổi trạng thái.

## Hướng dẫn gửi lại và chống tạo trùng

Có thể gửi lại an toàn khi:

- Timeout mạng.
- Phản hồi `5xx`.
- Không nhận được phản hồi.
- `429` sau khi đã chờ/backoff.

Không gửi lại nếu chưa chỉnh dữ liệu:

- `401`
- `403`
- `409`
- `422`

Khi gửi lại an toàn, gửi lại cùng payload với cùng `crm_admission_id`.

Khi gửi lại tài liệu, gửi lại cùng `crm_file_id` cho cùng một file CRM.

## Danh sách kiểm tra tích hợp

Trước khi gửi dữ liệu lên môi trường chính thức:

- Swinx cung cấp URL cơ sở của môi trường chính thức.
- Swinx cung cấp token tiếp nhận dữ liệu.
- CRM xác nhận danh sách IP nguồn đi ra của môi trường chính thức với Swinx.
- CRM lưu token trong hệ thống quản lý bí mật.
- CRM gọi thành công `GET /ping` từ IP nguồn đi ra của môi trường chính thức.
- CRM gửi một hồ sơ tuyển sinh kiểm thử tới `POST /applications`.
- CRM gửi lại cùng payload kiểm thử và xác nhận phản hồi là `200 OK`, không tạo hồ sơ tuyển sinh trùng.
- Nhân viên Swinx xác nhận hồ sơ xuất hiện ở trạng thái `pending`.
- Nhân viên Swinx xác nhận Người giám hộ và Tài liệu hiển thị đúng.
- CRM kiểm thử cách xử lý lỗi kiểm tra hợp lệ bằng một payload không hợp lệ ở môi trường không chính thức.
- CRM kiểm thử cách xử lý xung đột bằng một hồ sơ ở môi trường không chính thức đã được ghi danh hoặc từ chối.

## Các mục cần thống nhất

Trước khi gửi dữ liệu lên môi trường chính thức, xác nhận lại với Swinx:

- Có campus/program/intake/curriculum version mới chưa được liệt kê hay không.
- Có chuyên ngành (`intended_specialization`) mới hay không.
- Danh sách `file_type_code` có thay đổi hay không.
- IP nguồn đi ra của môi trường chính thức của CRM.
- Quy trình xoay vòng token.
- Số lượng yêu cầu dự kiến theo ngày và theo giờ cao điểm.
