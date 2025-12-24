## 1. Kết luận nhanh (TL;DR)

### ✅ Khuyến nghị

👉 **1 API chung khi login**:

```
GET /student/context
```

API này trả về:

- Survey gate
- User settings
- Feature flags
- Permissions nhẹ
- UI preferences

🚫 **Không nên**:

- `/survey/check`
- `/setting/theme`
- `/setting/notification`
- `/setting/xxx`

→ sẽ dẫn tới **API explosion + nhiều roundtrip**

---

## 2. Tư duy đúng: “Context API”, không phải “Settings API”

### ❌ Tư duy sai (rất hay gặp)

> Mỗi feature = 1 API

→ Login gọi 6–10 API
→ Mobile / FE lag
→ Rất khó cache

---

### ✅ Tư duy đúng (portal / LMS / ERP)

> **Login → lấy toàn bộ “context cần thiết để render app”**

Giống:

- Canvas
- Moodle
- SAP
- Notion
- Slack

---

## 3. Đề xuất cấu trúc API chuẩn

### `GET /student/context`

```json
{
  "survey_gate": {
    "blocked": true,
    "groups": {
      "course": [...],
      "department": [...]
    }
  },

  "settings": {
    "ui": {
      "theme": "system",
      "language": "vi",
      "compact_mode": false
    },
    "notifications": {
      "email": true,
      "push": false
    }
  },

  "feature_flags": {
    "room_booking": true,
    "wallet": true,
    "clubs": false
  },

  "permissions": {
    "can_book_room": true,
    "can_join_club": true
  }
}
```

👉 **FE chỉ cần 1 call duy nhất**

---

## 4. DB design: ĐỦ LINH HOẠT – KHÔNG BỊ KHÓA CỨNG

### A. Table gợi ý: `student_settings`

```sql
CREATE TABLE student_settings (
  student_id BIGINT UNSIGNED PRIMARY KEY,
  settings JSON NOT NULL,
  updated_at TIMESTAMP
);
```

### Ví dụ JSON

```json
{
    "ui": {
        "theme": "dark",
        "language": "vi"
    },
    "notifications": {
        "email": true,
        "push": false
    }
}
```

✔ Không cần migration mỗi lần thêm setting
✔ Dễ version
✔ Dễ cache

---

## 5. Nhưng Survey Gate có nên lưu vào settings không?

👉 **KHÔNG**

| Thứ         | Lý do                                       |
| ----------- | ------------------------------------------- |
| Survey gate | Derived data (tính toán từ form & response) |
| Settings    | User preference (persisted)                 |

👉 Survey gate:

- Tính động
- Cache ngắn hạn
- Không phải preference

---

## 6. Vì sao gọi chung tốt hơn gọi riêng?

### A. Performance

- 1 request
- 1 cache key
- 1 serialize

### B. Mobile rất thích

- Flutter / iOS cold start nhanh
- Ít race condition

### C. Version dễ

```json
"context_version": 3
```

---

## 7. Khi nào tách API riêng?

👉 **CHỈ khi:**

| Trường hợp | Ví dụ                     |
| ---------- | ------------------------- |
| Update     | `PATCH /student/settings` |
| Heavy data | Timeline, dashboard       |
| Realtime   | Notification list         |

---

## 8. Caching chiến lược (rất quan trọng)

### Cache key

```txt
student:{id}:context
```

### Invalidate khi:

- Submit survey
- Update settings
- Role change
- Semester change

👉 Có thể invalidate theo tag:

```php
Cache::tags(["student:$id"])->flush();
```

---

## 9. Flow chuẩn (FE + BE)

```
Login success
 ↓
GET /student/context
 ↓
Init app store (Pinia)
 ↓
Block portal? → show modal
 ↓
Render modules theo feature_flags
```
