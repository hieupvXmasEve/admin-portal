## Trang **Aggregate – Course Survey** cần hiển thị **CHỈ 4 NHÓM THÔNG TIN**

## 1️⃣ Course / Survey Context (Header)

> Để người xem biết **đang xem survey nào**

**BẮT BUỘC có**

- Course code + name
- Semester
- Form version
- Tổng số SV phải làm / đã làm

**Ví dụ**

```
IT101 – Introduction to Programming
Semester 2024A · Survey v2
Responses: 124 / 150 (82%)
```

## 2️⃣ Tổng quan toàn Course (Overall KPIs)

> Trả lời nhanh: **course này được đánh giá tốt hay không**

**Hiển thị**

- ⭐ **Overall average rating** (to 1 decimal)
- % Positive (rating ≥ 4)
- % Neutral (rating = 3)
- % Negative (rating ≤ 2)

**KHÔNG**

- Không text answer
- Không chi tiết từng câu

## 3️⃣ Aggregate theo **Section** (quan trọng nhất)

> Trả lời: **section nào ổn / section nào có vấn đề**

### Với MỖI section, hiển thị:

**A. Thông tin chính**

- Section title
- Số câu rating trong section

**B. Rating summary**

- ⭐ Average rating của section
- Rating distribution (1–5)
- % positive / negative

**C. Tín hiệu**

- Màu / icon:
    - 🟢 tốt
    - 🟡 trung bình
    - 🔴 thấp

**Ví dụ**

```
Teaching Quality
Avg: 4.2 ⭐
Positive: 78% · Negative: 6%
```

👉 **KHÔNG hiển thị text feedback**

## 4️⃣ Aggregate theo **Question (rating only)**

> Trả lời: **câu nào kéo điểm xuống / câu nào nổi bật**

### Với MỖI câu rating:

- Question text
- ⭐ Average rating
- Rating distribution (mini bar)

**Ví dụ**

```
Lecturer explains concepts clearly
Avg: 4.6 ⭐
```

👉 Click → sang **Detail page**

## 🚫 Những thứ KHÔNG hiển thị ở Aggregate

| Không hiển thị | Vì sao                 |
| -------------- | ---------------------- |
| Text answers   | Quá dài, không insight |
| Tên student    | Vi phạm anonymity      |
| Từng response  | Không phải dashboard   |
| Export CSV     | Để Detail              |

## 🧠 Mental model để nhớ

> **Aggregate = Dashboard**

- Nhìn 30 giây là hiểu course này ổn hay không
- Biết **chỗ nào cần đào sâu**
- Không đọc chữ dài

> **Detail = Investigation**

- Đọc feedback
- Lọc / tìm / export

## ✅ Checklist nhanh (để bạn dev)

**Aggregate course survey =**

- [x] Course info
- [x] Overall rating
- [x] Section-level rating
- [x] Question-level rating
- [x] Link sang Detail
