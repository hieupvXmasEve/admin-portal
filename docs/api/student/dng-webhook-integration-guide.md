# DNG Payment Webhook - Integration Guide

> Tài liệu hướng dẫn tích hợp webhook thanh toán DNG cho bên thứ 3.

---

## 1. Tổng quan

Hệ thống nhận callback từ DNG khi sinh viên thanh toán thành công. DNG gọi webhook **2 lần** cho mỗi giao dịch:

1. **Lần 1** - Thanh toán thành công (chưa có hóa đơn)
2. **Lần 2** - Đã xuất hóa đơn (có thông tin hóa đơn)

`PaymentId` và `TransactionId` trong callback thực tế có thể là mã nội bộ do bên thứ 3 sinh ra, không nhất thiết trùng 100% với `PaymentId` lúc tạo invoice ban đầu. Hệ thống ưu tiên match theo `ItemId + StudentId`.

---

## 2. Endpoint

```
POST {BASE_URL}/api/webhooks/dng/payment
Content-Type: application/json
```

| Thuộc tính   | Giá trị                                                               |
| ------------ | --------------------------------------------------------------------- |
| Method       | `POST`                                                                |
| Content-Type | `application/json`                                                    |
| Auth         | Không yêu cầu token                                                   |
| Xác thực     | Qua trường `CheckSum` ở cả hai callback; lần 1 dùng `InvoiceSerialNumber` rỗng |
| Rate limit   | 60 request/phút                                                       |

---

## 3. Request Payload

### 3.1. Các trường bắt buộc

| Trường       | Kiểu    | Mô tả                                         | Ví dụ         |
| ------------ | ------- | --------------------------------------------- | ------------- |
| `StudentId`  | string  | Mã sinh viên                                  | `"HE170001"`  |
| `PaymentId`  | string  | Mã giao dịch callback do DNG/bên thứ 3 trả về | `"12345678"`  |
| `Amount`     | numeric | Số tiền thanh toán (VNĐ)                      | `5000000`     |
| `CampusCode` | string  | Mã campus                                     | `"FPTUHN"`    |
| `ItemId`     | string  | Mã khoản phí                                  | `"ITEM001"`   |
| `CheckSum`   | string  | Chữ ký xác thực HMAC-SHA1                     | `"abc123..."` |

### 3.2. Các trường tùy chọn

| Trường                | Kiểu   | Mô tả                                  | Ví dụ                   |
| --------------------- | ------ | -------------------------------------- | ----------------------- |
| `PSPCode`             | string | Mã nhà cung cấp thanh toán (ngân hàng) | `"BIDV"`                |
| `FeeType`             | string | Loại phí                               | `"tuition"`             |
| `InvoiceSerialNumber` | string | Số serial hóa đơn (lần gọi thứ 2)      | `"1/001;K23TF"`         |
| `InvoiceDate`         | string | Ngày xuất hóa đơn (lần gọi thứ 2)      | `"2025-03-20T03:08:02"` |

> **Lưu ý:** Khi `InvoiceSerialNumber` VÀ `InvoiceDate` đều có giá trị → hệ thống xác nhận đã xuất hóa đơn.

> **Lưu ý:** Callback lần 1 thường **không có** `InvoiceSerialNumber`; hệ thống xác minh checksum với segment `InvoiceSerialNumber` rỗng. Business validation vẫn kiểm tra `StudentId`, `FeeType`, `Amount`.

### 3.3. Nguồn dữ liệu cho CheckSum

**Quan trọng:** Checksum trong webhook callback được tạo từ chính dữ liệu mà bên tích hợp đã gửi lên DNG khi tạo invoice, không phải từ các mã giao dịch callback (`PaymentId`, `TransactionId`) mà bên thứ 3 phát sinh sau đó.

Khi tạo invoice trên DNG (gọi `InsertNewRecord`), bên tích hợp gửi:

```json
{
    "ApiCode": "DEMO_API",
    "StudentId": "STUDENT-DEMO-001",
    "CampusCode": "CAMPUS-DEMO",
    "Type": "HP",
    "Amount": 10000,
    "ItemId": "swinx-reservation-demo-001",
    "Login": "DEMO_LOGIN",
    "CheckSum": "...",
    "StudentName": "Demo Student",
    "Email": "student@example.test",
    "EstimateTime": "05/26",
    "StudentAddress": "Demo address",
    "CCCD": ""
}
```

DNG lưu lại các trường: `StudentId` → `STUDENT-DEMO-001`, `CampusCode` → `CAMPUS-DEMO`, `Type` (FeeType) → `HP`, `Amount` → `10000`, `ItemId` → `swinx-reservation-demo-001`. **Hệ thống dùng dữ liệu push gốc này để verify callback**, không dùng `PaymentId` / `TransactionId` callback làm nguồn checksum.

### 3.4. Ví dụ payload callback

**Lần 1 - Thanh toán thành công (chưa có hóa đơn):**

```json
{
    "StudentId": "STUDENT-DEMO-001",
    "PaymentId": "DNG-PAY-DEMO-001",
    "Amount": 10000,
    "CampusCode": "CAMPUS-DEMO",
    "ItemId": "swinx-reservation-demo-001",
    "FeeType": "HP",
    "CheckSum": "hAQwb/sc5rqnueDydS5C4vrQax4%3d"
}
```

**Lần 2 - Đã xuất hóa đơn:**

```json
{
    "StudentId": "STUDENT-DEMO-001",
    "PaymentId": "DNG-PAY-DEMO-001",
    "Amount": 10000,
    "CampusCode": "CAMPUS-DEMO",
    "ItemId": "swinx-reservation-demo-001",
    "FeeType": "HP",
    "CheckSum": "...",
    "InvoiceSerialNumber": "1/001;K23TF",
    "InvoiceDate": "2026-03-26T10:00:00"
}
```

> **Lưu ý:** Trong môi trường thật, `PaymentId` callback có thể khác `ItemId` / `PaymentId` lúc tạo invoice. Vì vậy hệ thống không verify cứng `PaymentId` và `TransactionId` như business key bắt buộc.

---

## 4. Checksum (Chữ ký xác thực)

### 4.1. Thuật toán

1. **Ghép chuỗi** (không có dấu phân cách):

    ```
    ChecksumValue = AccessCode + ClientCode + Amount + InvoiceSerialNumber + StudentId + FeeType + CampusCode
    ```

    | Trường                | Nguồn                                                          | Giá trị từ ví dụ trên    |
    | --------------------- | -------------------------------------------------------------- | ------------------------ |
    | `AccessCode`          | Được cung cấp riêng (cùng với secret key)                      | `"<AccessCode>"`         |
    | `ClientCode`          | Được cung cấp riêng                                            | `"HC_ASIA"`              |
    | `Amount`              | `Amount` từ request `InsertNewRecord` gốc (`push_payload`)     | `"10000"` / `"10000.00"` |
    | `InvoiceSerialNumber` | Từ webhook payload (rỗng nếu chưa có hóa đơn)                  | `""` / `"1/001;K23TF"`   |
    | `StudentId`           | `StudentId` từ request `InsertNewRecord` gốc (`push_payload`)  | `"STUDENT-DEMO-001"`     |
    | `FeeType`             | `Type` từ request `InsertNewRecord` gốc (`push_payload`)       | `"HP"`                   |
    | `CampusCode`          | `CampusCode` từ request `InsertNewRecord` gốc (`push_payload`) | `"FAUHN"`                |

    Ví dụ (lần 1, chưa có hóa đơn):

    ```
    "<AccessCode>DEMO_CLIENT10000.00STUDENT-DEMO-001HPCAMPUS-DEMO"
    ```

    Ví dụ (lần 2, có hóa đơn):

    ```
    "<AccessCode>DEMO_CLIENT10000.001/001;K23TFSTUDENT-DEMO-001HPCAMPUS-DEMO"
    ```

    > **Lưu ý:** `Amount`, `StudentId`, `FeeType`, `CampusCode` được lấy từ payload gốc đã push sang DNG. `InvoiceSerialNumber` lấy từ callback; khi callback lần 1 không có trường này, checksum dùng segment rỗng.

2. **Tạo HMAC-SHA1** với secret key (được cung cấp riêng):

    ```
    hash = HMAC-SHA1(ChecksumValue, SECRET_KEY)  // binary output
    ```

3. **Encode base64:**

    ```
    base64 = Base64Encode(hash)
    ```

4. **Thay thế ký tự:**
    - `=` → `%3d`
    - ` ` (space) → `+`

### 4.2. Ví dụ pseudocode

```python
import hmac, hashlib, base64

def generate_checksum(access_code, client_code, amount, invoice_serial_number,
                      student_id, fee_type, campus_code, secret_key):
    value = access_code + client_code + str(amount) + invoice_serial_number + student_id + fee_type + campus_code

    hash_bytes = hmac.new(
        secret_key.encode('utf-8'),
        value.encode('utf-8'),
        hashlib.sha1
    ).digest()

    checksum = base64.b64encode(hash_bytes).decode('utf-8')
    checksum = checksum.replace('=', '%3d').replace(' ', '+')

    return checksum
```

```javascript
const crypto = require('crypto');

function generateChecksum(accessCode, clientCode, amount, invoiceSerialNumber, studentId, feeType, campusCode, secretKey) {
    const value = accessCode + clientCode + String(amount) + invoiceSerialNumber + studentId + feeType + campusCode;

    const hash = crypto.createHmac('sha1', secretKey).update(value, 'utf-8').digest();

    let checksum = hash.toString('base64');
    checksum = checksum.replace(/=/g, '%3d').replace(/ /g, '+');

    return checksum;
}
```

### 4.3. Secret Key

Secret key sẽ được cung cấp riêng qua kênh bảo mật. **Không chia sẻ qua email hoặc chat không mã hóa.**

---

## 5. Response

### 5.1. Thành công (200)

```json
{
    "Code": 200,
    "Type": "Success",
    "Message": "Accepted",
    "data": null
}
```

### 5.2. Đã nhận và đưa vào hàng đợi xử lý (200)

```json
{
    "Code": 200,
    "Type": "Success",
    "Message": "Accepted",
    "data": null
}
```

> Hệ thống hiện áp dụng mô hình **inbox-first**: callback tới đâu lưu raw payload tới đó vào `dng_webhook_events`, sau đó queue mới kiểm tra checksum, dedup nghiệp vụ, mismatch, và side effect.

### 5.3. Checksum không hợp lệ

Checksum không còn bị reject ngay tại HTTP layer. Callback vẫn được lưu vào inbox, sau đó worker async sẽ gán `processing_status = mismatch` và `error_category = checksum` nếu verify thất bại.

- Callback lần 1 (không có `InvoiceSerialNumber`) → verify checksum với segment rỗng
- Callback lần 2 (có `InvoiceSerialNumber`) → verify checksum với invoice serial

### 5.4. Payload thiếu trường / không map được request

Callback vẫn được lưu trước để phục vụ forensic/debug. Sau đó worker sẽ phân loại:

- `failed_terminal` nếu payload không đủ để resolve nghiệp vụ
- `mismatch` nếu checksum hoặc business fields `StudentId`, `FeeType`, `Amount` không khớp
- `failed_retryable` nếu lỗi xử lý tạm thời

---

## 6. Xử lý lỗi & Retry

| Tình huống           | HTTP Status | Hành động bên gọi                   |
| -------------------- | ----------- | ----------------------------------- |
| Thành công           | 200         | Không cần retry                     |
| Callback đã nhận     | 200         | Không cần retry HTTP layer          |
| Checksum sai         | 200         | Kiểm tra dữ liệu checksum async     |
| Thiếu trường         | 200         | Kiểm tra bản ghi inbox và log xử lý |
| Server error         | 500         | Retry sau 10s, tối đa 5 lần         |
| Timeout (>30s)       | -           | Retry sau 30s, tối đa 5 lần         |

**Khuyến nghị retry:**

- Retry tối đa 5 lần
- Backoff: 10s → 30s → 60s → 300s → 600s
- Chỉ retry khi HTTP 5xx hoặc timeout
- Với HTTP 200 nhưng xử lý async bị `mismatch`, cần đối chiếu `dng_webhook_events`

---

## 7. Luồng xử lý (Sequence)

```
DNG                          Hệ thống SwinX
 │                                │
 │  POST /api/webhooks/dng/payment│
 │  (Lần 1: không có invoice)    │
 │ ──────────────────────────────>│
  │                                │── Lưu raw webhook event vào inbox
  │       200 {"Message":"Accepted"}│
  │ <──────────────────────────────│
  │                                │── Queue xử lý async
  │                                │── Resolve local payment request (ưu tiên `ItemId + StudentId`)
  │                                │── Verify CheckSum với invoice serial rỗng
  │                                │── Verify `StudentId`, `FeeType`, `Amount`
  │                                │── Cập nhật trạng thái: paid_uninvoiced
 │                                │
 │  POST /api/webhooks/dng/payment│
 │  (Lần 2: có invoice)          │
 │ ──────────────────────────────>│
  │                                │── Lưu raw webhook event vào inbox
  │       200 {"Message":"Accepted"}│
  │ <──────────────────────────────│
  │                                │── Queue xử lý async
  │                                │── Resolve local payment request
  │                                │── Verify CheckSum
  │                                │── Verify `StudentId`, `FeeType`, `Amount`
  │                                │── Transition + bridge payment
  │                                │── Cập nhật trạng thái: paid_invoiced
```

## 8. Liên hệ hỗ trợ

Nếu gặp vấn đề khi tích hợp, vui lòng liên hệ team kỹ thuật để được hỗ trợ:

- Cung cấp `PaymentId`, `ItemId` và thời gian gọi webhook để debug
- Cung cấp full request/response để đối chiếu
