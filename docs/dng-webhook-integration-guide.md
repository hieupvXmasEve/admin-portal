# DNG Payment Webhook - Integration Guide

> Tài liệu hướng dẫn tích hợp webhook thanh toán DNG cho bên thứ 3.

---

## 1. Tổng quan

Hệ thống nhận callback từ DNG khi sinh viên thanh toán thành công. DNG gọi webhook **2 lần** cho mỗi giao dịch:

1. **Lần 1** - Thanh toán thành công (chưa có hóa đơn)
2. **Lần 2** - Đã xuất hóa đơn (có thông tin hóa đơn)

Cả 2 lần dùng cùng `PaymentId`.

---

## 2. Endpoint

```
POST {BASE_URL}/api/webhooks/dng/payment
Content-Type: application/json
```

| Thuộc tính    | Giá trị                |
|---------------|------------------------|
| Method        | `POST`                 |
| Content-Type  | `application/json`     |
| Auth          | Không yêu cầu token    |
| Xác thực      | Qua trường `CheckSum`  |
| Rate limit    | 60 request/phút        |

---

## 3. Request Payload

### 3.1. Các trường bắt buộc

| Trường        | Kiểu    | Mô tả                                      | Ví dụ              |
|---------------|---------|---------------------------------------------|---------------------|
| `StudentId`   | string  | Mã sinh viên                                | `"HE170001"`        |
| `PaymentId`   | string  | Mã giao dịch DNG (unique cho mỗi khoản nợ) | `"12345678"`        |
| `Amount`      | numeric | Số tiền thanh toán (VNĐ)                    | `5000000`           |
| `CampusCode`  | string  | Mã campus                                   | `"FPTUHN"`          |
| `ItemId`      | string  | Mã khoản phí                                | `"ITEM001"`         |
| `CheckSum`    | string  | Chữ ký xác thực HMAC-SHA1                   | `"abc123..."`       |

### 3.2. Các trường tùy chọn

| Trường                  | Kiểu    | Mô tả                                | Ví dụ                        |
|-------------------------|---------|---------------------------------------|-------------------------------|
| `PSPCode`               | string  | Mã nhà cung cấp thanh toán (ngân hàng) | `"BIDV"`                    |
| `FeeType`               | string  | Loại phí                              | `"tuition"`                   |
| `InvoiceSerialNumber`   | string  | Số serial hóa đơn (lần gọi thứ 2)    | `"1/001;K23TF"`               |
| `InvoiceDate`           | string  | Ngày xuất hóa đơn (lần gọi thứ 2)    | `"2025-03-20T03:08:02"`       |

> **Lưu ý:** Khi `InvoiceSerialNumber` VÀ `InvoiceDate` đều có giá trị → hệ thống xác nhận đã xuất hóa đơn.

### 3.3. Nguồn dữ liệu cho CheckSum

**Quan trọng:** Checksum trong webhook callback được tạo từ chính dữ liệu mà bên tích hợp đã gửi lên DNG khi tạo invoice, không phải từ payload callback.

Khi tạo invoice trên DNG (gọi `InsertNewRecord`), bên tích hợp gửi:

```json
{
  "ApiCode": "HC_SWB",
  "StudentId": "AUH121620",
  "CampusCode": "FAUHN",
  "Type": "HP",
  "Amount": 10000,
  "ItemId": "AUH121620_1774517167168",
  "Login": "HC_SWB",
  "CheckSum": "...",
  "StudentName": "PHẠM TIẾN ĐỒNG",
  "Email": "phamdongdongpham12@gmail.com",
  "EstimateTime": "05/26",
  "StudentAddress": "XÓM 10 THÔN VÂN ĐÌNH, VÂN ĐÌNH, ỨNG HÒA, HÀ NỘI",
  "CCCD": "001206079828"
}
```

DNG lưu lại các trường: `StudentId` → `AUH121620`, `CampusCode` → `FAUHN`, `Type` (FeeType) → `HP`, `Amount` → `10000`, `ItemId` → `AUH121620_1774517167168`. **Chính các trường này được dùng để tạo checksum cho webhook callback.**

### 3.4. Ví dụ payload callback

**Lần 1 - Thanh toán thành công (chưa có hóa đơn):**

```json
{
  "StudentId": "AUH121620",
  "PaymentId": "AUH121620_1774517167168",
  "Amount": 10000,
  "CampusCode": "FAUHN",
  "ItemId": "AUH121620_1774517167168",
  "FeeType": "HP",
  "CheckSum": "hAQwb/sc5rqnueDydS5C4vrQax4%3d"
}
```

**Lần 2 - Đã xuất hóa đơn:**

```json
{
  "StudentId": "AUH121620",
  "PaymentId": "AUH121620_1774517167168",
  "Amount": 10000,
  "CampusCode": "FAUHN",
  "ItemId": "AUH121620_1774517167168",
  "FeeType": "HP",
  "CheckSum": "...",
  "InvoiceSerialNumber": "1/001;K23TF",
  "InvoiceDate": "2026-03-26T10:00:00"
}
```

> **Lưu ý:** `PaymentId` = `ItemId` mà bên tích hợp tự sinh khi tạo invoice (thường là `{StudentId}_{timestamp}`).

---

## 4. Checksum (Chữ ký xác thực)

### 4.1. Thuật toán

1. **Ghép chuỗi** (không có dấu phân cách):
   ```
   ChecksumValue = AccessCode + ClientCode + Amount + InvoiceSerialNumber + StudentId + FeeType + CampusCode
   ```

   | Trường                | Nguồn                                          | Giá trị từ ví dụ trên   |
   |-----------------------|------------------------------------------------|--------------------------|
   | `AccessCode`          | Được cung cấp riêng (cùng với secret key)      | `"<AccessCode>"`         |
   | `ClientCode`          | Được cung cấp riêng                            | `"HC_ASIA"`              |
   | `Amount`              | `Amount` từ request `InsertNewRecord` gốc      | `"10000.00"`             |
   | `InvoiceSerialNumber` | Từ webhook payload (rỗng nếu chưa có hóa đơn) | `""` / `"1/001;K23TF"`   |
   | `StudentId`           | `StudentId` từ request `InsertNewRecord` gốc   | `"AUH121620"`            |
   | `FeeType`             | `Type` từ request `InsertNewRecord` gốc        | `"HP"`                   |
   | `CampusCode`          | `CampusCode` từ request `InsertNewRecord` gốc  | `"FAUHN"`                |

   Ví dụ (lần 1, chưa có hóa đơn):
   ```
   "<AccessCode>HC_ASIA10000.00AUH121620HPFAUHN"
   ```

   Ví dụ (lần 2, có hóa đơn):
   ```
   "<AccessCode>HC_ASIA10000.001/001;K23TFAUH121620HPFAUHN"
   ```

   > **Lưu ý:** `Amount` phải đúng định dạng decimal 2 chữ số như đã lưu (vd: `10000.00`), `StudentId` và `FeeType` lấy **nguyên gốc** từ request `InsertNewRecord`, không phải từ payload callback.

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

function generateChecksum(accessCode, clientCode, amount, invoiceSerialNumber,
                          studentId, feeType, campusCode, secretKey) {
    const value = accessCode + clientCode + String(amount) + invoiceSerialNumber + studentId + feeType + campusCode;

    const hash = crypto.createHmac('sha1', secretKey)
        .update(value, 'utf-8')
        .digest();

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

### 5.2. Đã nhận trước đó - Duplicate (200)

```json
{
  "Code": 200,
  "Type": "Success",
  "Message": "Already received",
  "data": null
}
```

> Hệ thống đảm bảo **idempotent** — gửi cùng payload nhiều lần không gây side effect.

### 5.3. Checksum không hợp lệ (401)

```json
{
  "Code": 401,
  "Type": "Error",
  "Message": "Invalid checksum",
  "data": null
}
```

### 5.4. Thiếu trường bắt buộc (422)

```json
{
  "message": "The StudentId field is required. (and X more errors)",
  "errors": {
    "StudentId": ["The StudentId field is required."],
    "PaymentId": ["The PaymentId field is required."]
  }
}
```

---

## 6. Xử lý lỗi & Retry

| Tình huống                     | HTTP Status | Hành động bên gọi            |
|--------------------------------|-------------|-------------------------------|
| Thành công                     | 200         | Không cần retry               |
| Duplicate payload              | 200         | Không cần retry               |
| Checksum sai                   | 401         | Kiểm tra lại logic checksum   |
| Thiếu trường                   | 422         | Bổ sung trường thiếu          |
| Server error                   | 500         | Retry sau 10s, tối đa 5 lần  |
| Timeout (>30s)                 | -           | Retry sau 30s, tối đa 5 lần  |

**Khuyến nghị retry:**
- Retry tối đa 5 lần
- Backoff: 10s → 30s → 60s → 300s → 600s
- Chỉ retry khi HTTP 5xx hoặc timeout
- **Không retry** khi 401 hoặc 422

---

## 7. Luồng xử lý (Sequence)

```
DNG                          Hệ thống SwinX
 │                                │
 │  POST /api/webhooks/dng/payment│
 │  (Lần 1: không có invoice)    │
 │ ──────────────────────────────>│
 │                                │── Validate fields
 │                                │── Verify CheckSum
 │                                │── Dedup (payload hash)
 │                                │── Lưu webhook event
 │       200 {"Message":"Accepted"}│
 │ <──────────────────────────────│
 │                                │── Xử lý async (queue)
 │                                │── Cập nhật trạng thái: paid_uninvoiced
 │                                │
 │  POST /api/webhooks/dng/payment│
 │  (Lần 2: có invoice)          │
 │ ──────────────────────────────>│
 │                                │── Validate & Verify
 │                                │── Dedup (payload hash mới)
 │                                │── Lưu webhook event
 │       200 {"Message":"Accepted"}│
 │ <──────────────────────────────│
 │                                │── Xử lý async (queue)
 │                                │── Cập nhật trạng thái: paid_invoiced
```


## 8. Liên hệ hỗ trợ

Nếu gặp vấn đề khi tích hợp, vui lòng liên hệ team kỹ thuật để được hỗ trợ:

- Cung cấp `PaymentId` và thời gian gọi webhook để debug
- Cung cấp full request/response để đối chiếu
