# brainstorm: retake exam fee and regrading flow

## Goal

Thiet ke workflow thi lai cho mon fail sau khi GV complete course: GV de cu sinh vien du dieu kien, dao tao phe duyet, sinh vien tu dang ky va dong phi tren Portal, qua han khong dong phi thi chuyen sang hoc lai, dong phi xong thi vao danh sach cho thi lai, dao tao mo lop thi lai + lich + diem danh + nhap/sync diem, sau do chot Pass/Fail cuoi cung.

## What I already know

* User flow mong muon:
* B1 GV complete course xong thi thay list sinh vien fail va tick ai du tu cach/thai do tot de chuyen dao tao.
* B2 Dao tao phe duyet danh sach nhan tu GV, roi moi cho phep sinh vien dang ky thi lai.
* B3 Sinh vien nhan thong bao, vao Portal tu click dang ky; HQ review phi tren admin roi tao phi de sinh vien thanh toan tren Portal.
* B3.1 Qua han dang ky ma chua dong phi thi auto chuyen "Hoc lai".
* B3.2 Dong phi xong thi vao danh sach cho thi lai.
* B4 Dao tao chot danh sach, tao lop thi lai de day lich len Calendar cua sinh vien, co diem danh va noi nhap diem.
* B5 Diem thi lai sync tu Canvas hoac nhap tay, roi chot ket qua Pass/Fail cuoi cung.
* Hien tai complete course di qua `app/Modules/Academic/Actions/MarkCourseOfferingCompletedAction.php` -> `app/Services/CourseCompletionService.php`.
* `CourseCompletionService` hien finalize diem, cap nhat `course_registrations.registration_status = completed`, gui event `academic.course_completed`, nhung chua co approval flow cho thi lai.
* Fail list hien co san o `routes/web/failed-students.php`, `app/Http/Controllers/Web/FailedStudentsController.php`, `app/Services/FailedStudentsService.php`.
* Fail list lay tu `academic_records.is_passed = false`, co thong ke `retake_eligible_count`, co `attempt_number`, `attendance_percentage`, `override_pass`.
* He thong da co baseline retake data:
* `course_registrations`: `attempt_number`, `is_retake`, `original_registration_id`, `retake_fee`, `is_retake_paid`
* `academic_records`: `attempt_number`, `is_repeat_course`, `original_record_id`, `override_pass`, `is_passed`, `override_reason`
* Finance da support charge type `retake_fee` qua `app/Models/FinanceCharge.php` va `app/Modules/Finance/Services/FinanceChargeService.php`.
* DNG fee type map `retake_fee -> PTL` tai `app/Modules/Finance/Dng/Support/DngFeeTypeOptions.php`.
* Student portal da co finance API de xem charges, invoices, DNG requests, QR/installment:
* `routes/api/v1/student.php`
* `app/Modules/Finance/Http/Api/Student/StudentFinanceController.php`
* Student portal da co calendar API, nhung hien doc tu semester + `academic_calendar_events`, chua thay event lop thi lai:
* `app/Services/V1/Student/CalendarService.php`
* Canvas grade sync da co cho course offering mapped:
* `routes/web/canvas.php`
* `app/Http/Controllers/Web/Canvas/CanvasSyllabusController.php`
* Repo da co mot `CourseRetakeService`, nhung day la flow cu/chua khop nhu cau moi:
* ghi field `status`, `retake_reason` khong khop model `CourseRegistration`
* chua co approval state machine GV -> Dao tao -> Portal -> Finance -> Exam

## Assumptions (temporary)

* Thi lai trong bai toan nay la **retake exam cua mon da fail**, khac voi hoc lai full subject.
* "Hoc lai" la nhanh fallback sau khi da duoc phe duyet thi lai nhung sinh vien khong hoan tat thanh toan dung han.
* Phi thi lai duoc tao duoi dang finance charge/DNG request, khong phai tao invoice tu tay ngoai settlement model.
* Lop thi lai o Buoc 4 se dung hoac mo rong entity `course_offerings` / `class_sessions`, thay vi mot lich thi rieng tach biet.
* Diem thi lai phai tro thanh source of truth cho ket qua cuoi cung cua mon, khong chi luu ghi chu bo sung.

## Open Questions

* Khong con blocker nao cho MVP sau vong review hien tai.

## Requirements (evolving)

* Sau khi complete course, he thong phai tao/refresh danh sach fail cho course offering va cho GV de cu ung vien du dieu kien thi lai.
* Workflow thi lai phai dung `retake case`/`retake exam request` rieng lam source of truth, tach khoi `course_registrations` trong giai doan approval/payment/scheduling.
* Ket qua thi lai phai tao attempt moi va giu nguyen attempt fail cu.
* He thong phai co quy tac xac dinh ket qua cuoi cung cua mon dua tren attempt thi lai hop le moi nhat.
* GPA phai tinh theo **diem cao nhat giua cac attempt cua cung 1 mon**, khong cong chong tat ca attempt vao GPA.
* Prerequisite, graduation, va trang thai dat mon phai theo rule: chi can co it nhat 1 attempt pass la mon duoc tinh dat.
* GV phai co tieu chi va ghi nhan quyet dinh de cu thi lai tren tung sinh vien fail.
* B1 phai theo rule hybrid: he thong loai truoc cac case chac chan khong hop le theo hard rules (vi du diem danh khong du, vi pham di hoc muon qua nguong, dieu kien ky luat/nep hoc neu co), sau do GV moi quyet dinh nominate/not nominate trong tap con lai.
* Hard-rule eligibility o B1 phai ho tro cau hinh theo `unit` hoac theo chuong trinh/campus, khong chi 1 rule global duy nhat.
* Neu dong thoi ton tai hard-rules theo `unit` va theo chuong trinh/campus, rule theo `unit` phai duoc uu tien override rule cap cao hon.
* So lan duoc mo `retake case` cho cung 1 `unit` phai bi gioi han toi da N lan theo rule hoc vu cau hinh.
* Mot lan retake chi duoc tinh la da "su dung" khi da tao attempt retake thuc te, tuc la da co ket qua `final pass/fail/no-show`.
* Cac role staff (GV, Dao tao, HQ) xu ly pending actions qua man hinh danh sach + bo loc theo trang thai; MVP khong bat buoc co inbox/task queue rieng theo role.
* Dao tao phai co man hinh danh sach/filter de phe duyet hoac tu choi de cu tu GV.
* Neu dao tao reject de cu tu GV thi retake case dong luon, khong co luong nominate lai/resubmit trong cung workflow thuong.
* Neu attempt fail goc sau do bi sua thanh `pass` trong luc retake case dang mo, retake case phai tu dong dong lai.
* B2 phai tach 2 state: dao tao approve eligibility truoc, sau do staff/dao tao mo quyen dang ky tren Portal bang mot thao tac rieng.
* Registration window phai mo theo dot cua tung mon/lop thi lai, nhieu sinh vien dung chung 1 cua so dang ky.
* Dao tao nhap tay `registration window` va `batch close` khi mo batch.
* Dot thi lai phai gom nhieu `retake case` cung mon trong cung `campus + term` vao 1 batch chung.
* He thong phai tu de xuat `retake batch` theo `unit + campus + term`, sau do dao tao xac nhan/mo batch thay vi tu tao hoan toan hoac staff tao tay tu dau.
* He thong duoc phep de xuat `retake batch` som, nhung batch chi mo/chinh thuc khi dao tao thay du dieu kien va xac nhan, hoac staff override thu cong.
* Chi sinh vien duoc dao tao cho phep moi thay CTA dang ky thi lai tren Portal.
* Sinh vien tu dang ky trong cua so thoi gian hop le; he thong gui/thay notification ro trang thai qua `in-app/portal + email`.
* Notification bat buoc toi SV trong MVP phai gom it nhat cac moc: mo dang ky, charge duoc tao, sap het han/thanh toan qua han, lich thi/session duoc xep, va ket qua cuoi cung duoc chot.
* Neu SV da duoc mo dang ky nhung khong bam dang ky truoc khi het `registration window`, case phai tu dong chuyen sang `Hoc lai`.
* Sau khi SV bam dang ky, case phai vao trang thai cho HQ/Admin finance review; chi sau review tung case moi duoc tao charge/DNG request.
* Sau khi SV da bam dang ky, SV khong duoc tu huy; chi staff moi co quyen huy/withdraw case theo nghiep vu.
* Staff chi duoc huy `retake case` theo flow thuong truoc khi case `paid`; neu da `paid` thi khong duoc huy theo flow thuong.
* HQ/Admin finance phai review va tao phi thi lai de sinh vien thanh toan tren Portal.
* HQ/Admin finance phai lay `unit.retake_fee` lam amount mac dinh, nhung duoc sua `amount` va `due date` tung case truoc khi tao charge/DNG request.
* HQ chi duoc tao charge trong `registration window`.
* Han thanh toan duoc tach rieng sau khi HQ tao charge, nhung khong duoc muon hon han chot cua `retake batch`.
* Neu het `registration window` ma HQ chua tao charge cho case da dang ky, case phai tu dong chuyen sang `Hoc lai`.
* Sau khi SV thanh toan thanh cong, case phai vao waiting list cua dot thi lai; he thong tu de xuat danh sach du dieu kien dua tren case da paid, va dao tao xac nhan cuoi danh sach du thi.
* Neu sinh vien khong thanh toan dung han thi workflow phai auto chuyen sang nhanh "Hoc lai".
* He thong phai co daily job de quet retake case overdue unpaid va cho staff bam nut manual de chuyen ngay neu can.
* Khi retake case da roi sang nhanh `Hoc lai`, flow retake cho chinh lan fail do phai ket thuc, khong mo lai case retake trong workflow thuong.
* Nhánh `Hoc lai` trong MVP chi doi status, khong tu tao workflow/record hoc lai tiep theo.
* Cac auto-transition sang `Hoc lai` phai luu `reason code` chuan hoa, system-defined de phuc vu audit/reporting.
* O cac thao tac manual cua staff (vi du huy case, bam chuyen `Hoc lai`, reject diem tay), bat buoc phai nhap ca `reason code` chuan hoa va `comment`.
* Dao tao phai xac nhan roster cuoi truoc, sau do moi tao `course_offering` rieng cho thi lai, day lich sang portal calendar, va co diem danh + nhap diem.
* `Course offering` thi lai phai de dao tao chon tay giang vien/can bo phu trach, khong bat buoc thua ke tu offering goc.
* Cau truc session cua `course_offering` thi lai phai linh hoat theo tung batch; dao tao co the tao 1 hoac nhieu session tuy nhu cau van hanh.
* Trong cung 1 dot thi lai, moi student chi duoc ton tai trong duy nhat 1 session.
* He thong phai de xuat phan student vao session thi lai, va dao tao co quyen xac nhan hoac chinh tay truoc khi chot.
* Session thi lai phai co capacity do dao tao nhap tay cho tung session.
* Attendance/check-in chi bat buoc tai `exam` session; day la moc hoc vu de xac dinh co mat/vang thi.
* Diem thi lai phai lay Canvas lam nguon chinh; manual input chi duoc dung nhu fallback khi khong map/sync Canvas duoc.
* Khi Canvas khong map/sync duoc, giang vien hoac can bo phu trach la nguoi nhap `final retake score` fallback; dao tao thuc hien review.
* Manual fallback score chi co hieu luc sau khi dao tao approve; truoc do diem o trang thai cho review.
* Dao tao khi review manual fallback score chi co quyen `approve/reject`, khong duoc sua truc tiep diem.
* Neu dao tao reject `manual fallback score`, workflow phan hoi phai tra ve cho giang vien/can bo phu trach nhap lai; dao tao khong duoc tu sua diem trong buoc review.
* Diem thi lai phai duoc ghi nhan duoi dang 1 `final retake score` cho attempt moi.
* Pass/Fail cua attempt thi lai phai dung lai passing rule/grade scale hien tai cua `unit`, khong tao threshold rieng cho retake exam.
* Diem thi lai phai co 2 cach vao he thong: sync tu Canvas hoac nhap tay fallback.
* Neu SV da vao roster/scheduled nhung `no-show` o ky thi lai, he thong phai tao attempt moi va chot ket qua `final fail/no-show` thay vi bo qua khong tao attempt.
* Sau `final fail/no-show` cua ky thi lai, case phai tu dong chuyen sang nhanh `Hoc lai`.
* He thong phai chot ket qua cuoi cung Pass/Fail tren ho so hoc tap mot cach audit duoc.
* Toan bo workflow phai campus-scoped, co permission ro cho GV, Dao tao, HQ/Admin finance.
* He thong phai co audit trail va thong bao cho cac moc trang thai quan trong.

## Acceptance Criteria (evolving)

* [ ] Co state machine ro rang cho `retake case`: fail -> lecturer_nominated -> training_approved_eligibility/training_rejected -> registration_opened -> student_registered -> finance_review_pending -> charge_created -> payment_pending/paid -> waiting_listed -> roster_confirmed -> scheduled -> assessed -> final_pass/final_fail -> fallback_repeat_course.
* [ ] `Retake case` la source of truth cho approval/payment/scheduling/result; `course_registrations` khong bi dung lam workflow chinh tu dau.
* [ ] Sau thi lai, he thong tao duoc attempt moi va van giu duoc record fail cu.
* [ ] GPA chi tinh tren attempt co diem cao nhat cua moi mon theo rule da chot.
* [ ] Mon duoc xem la dat cho prerequisite/graduation/status neu ton tai it nhat 1 attempt pass hop le.
* [ ] Complete Course co diem mo rong de GV thao tac danh sach fail ngay sau khi finalize.
* [ ] Danh sach fail de GV nominate da loai san cac case vi pham hard rules; GV chi thao tac tren tap con hop le va quyet dinh nominate/not nominate duoc audit.
* [ ] Eligibility engine o B1 doc duoc hard-rules theo `unit` hoac theo chuong trinh/campus.
* [ ] Khi co dong thoi rule theo `unit` va theo chuong trinh/campus, engine ap dung precedence: `unit` override rule cap cao hon.
* [ ] Eligibility engine enforce duoc gioi han toi da N lan retake cho cung 1 `unit` theo rule hoc vu cau hinh.
* [ ] He thong chi tru 1 lan retake vao quota N khi da phat sinh attempt retake thuc te voi ket qua `final pass/fail/no-show`.
* [ ] Cac role staff xu ly pending actions qua man hinh danh sach + bo loc theo trang thai; khong phu thuoc inbox/task queue rieng.
* [ ] Neu dao tao reject de cu thi retake case dong luon; khong co resubmit trong flow thuong.
* [ ] Neu attempt fail goc duoc sua thanh `pass` trong luc retake case dang mo, he thong tu dong dong retake case.
* [ ] Dao tao phe duyet eligibility va mo quyen dang ky la 2 moc state rieng, audit duoc, khong gom chung thanh 1 thao tac.
* [ ] Notification toi SV o cac moc trong MVP di qua `in-app/portal + email`.
* [ ] Notification toi SV duoc gui it nhat o cac moc: mo dang ky, charge duoc tao, sap het han/thanh toan qua han, lich thi/session duoc xep, ket qua cuoi cung duoc chot.
* [ ] Dao tao nhap tay duoc `registration window` va `batch close` khi mo `retake batch`.
* [ ] Case da duoc mo dang ky nhung het `registration window` ma SV khong dang ky thi tu dong chuyen sang `Hoc lai`.
* [ ] Khi retake case da sang nhanh `Hoc lai`, khong co luong mo lai retake case cho chinh lan fail do trong workflow thuong.
* [ ] Nhanh `Hoc lai` trong MVP chi doi status, khong tu dong tao workflow hoc lai tiep theo.
* [ ] Cac auto-transition sang `Hoc lai` luu duoc `reason code` chuan hoa de audit/reporting.
* [ ] Cac thao tac manual cua staff bat buoc nhap ca `reason code` chuan hoa va `comment`.
* [ ] Dao tao co man hinh phe duyet danh sach thi lai va chot danh sach du thi.
* [ ] Portal chi hien dang ky thi lai cho sinh vien da duoc approve va dang trong registration window chung cua dot thi lai tuong ung.
* [ ] Cac `retake case` cung `unit + campus + term` co the duoc gom vao cung 1 `retake batch`/dot thi lai thay vi tach theo tung offering goc.
* [ ] He thong tu de xuat `retake batch` theo `unit + campus + term`, va batch chi duoc xem la mo/chinh thuc sau khi dao tao xac nhan.
* [ ] `Retake batch` co the ton tai o trang thai de xuat som; batch chi mo khi dao tao xac nhan du dieu kien hoac chu dong override.
* [ ] Sau khi SV bam dang ky, case vao hang cho HQ/Admin finance review; charge khong tu tao ngay luc student submit.
* [ ] Sau khi SV submit dang ky, portal khong co nut tu huy; chi staff co quyen huy/withdraw case va phai audit duoc.
* [ ] Staff chi huy duoc case theo flow thuong truoc khi `paid`; case da `paid` khong di qua luong huy thong thuong.
* [ ] Finance tao duoc phi thi lai, portal xem duoc charge/invoice/DNG request va thanh toan duoc.
* [ ] Finance form prefill `unit.retake_fee`, nhung HQ sua duoc `amount` va `due date` tung case truoc khi tao charge.
* [ ] HQ chi tao duoc charge trong `registration window`.
* [ ] Case da dang ky nhung het `registration window` ma chua co charge thi tu dong chuyen sang `Hoc lai`.
* [ ] `Payment deadline` duoc tach rieng sau khi tao charge, nhung khong vuot qua han chot cua `retake batch`.
* [ ] Sau khi case paid, case vao waiting list cua `retake batch`; he thong tu de xuat danh sach du thi va dao tao xac nhan cuoi truoc khi scheduling.
* [ ] Qua han khong thanh toan thi case auto vao trang thai "Hoc lai" qua daily job, va staff co them nut manual transition.
* [ ] Dao tao xac nhan roster cuoi xong roi moi tao `course_offering` thi lai; khong tao offering truoc khi roster chua chot.
* [ ] Sau khi tao `course_offering` thi lai, sinh vien thay lich tren calendar va staff co diem danh/nhap diem.
* [ ] `Course offering` thi lai cho phep dao tao chon tay giang vien/can bo phu trach.
* [ ] `Course offering` thi lai cho phep tao 1 hoac nhieu session tuy batch; moi student chi duoc gan vao 1 session duy nhat trong dot thi lai do.
* [ ] He thong de xuat duoc viec phan student vao session, va dao tao co the xac nhan/chinh tay truoc khi chot roster session.
* [ ] Moi session thi lai co capacity do dao tao nhap tay.
* [ ] Attendance/check-in chi xay ra o `exam` session, va `no-show` duoc xac dinh dua tren session nay.
* [ ] Diem thi lai vao he thong qua Canvas sync la primary path; manual input chi dung khi khong map/sync duoc, va ket qua cuoi cung audit duoc.
* [ ] Neu Canvas khong sync duoc, giang vien/can bo phu trach nhap `final retake score` fallback va dao tao co buoc review theo rule da chot.
* [ ] Manual fallback score chi co hieu luc sau khi dao tao approve.
* [ ] Dao tao review manual fallback score theo quyen `approve/reject` thuan tuy; khong sua diem truc tiep trong buoc review.
* [ ] Neu manual fallback score bi reject, he thong tra score do ve cho giang vien/can bo phu trach nhap lai va audit duoc.
* [ ] Attempt thi lai moi chi nhan 1 `final retake score`; khong bat buoc sync/import full breakdown cho MVP.
* [ ] Pass/Fail cua attempt thi lai duoc xac dinh bang passing rule/grade scale hien co cua `unit`.
* [ ] Case `no-show` o ky thi lai van tao attempt moi va duoc chot thanh `final fail/no-show`, audit duoc.
* [ ] Case `final fail/no-show` tu dong chuyen sang nhanh `Hoc lai`.
* [ ] Khong doan field/relationship; moi quyet dinh dua tren schema/code thuc te.

## Definition of Done (team quality bar)

* Chot duoc source of truth entity cho retake workflow
* Chot duoc quy tac cap nhat transcript/academic record sau thi lai
* Chot duoc ownership role + permission + trigger thong bao
* Chot duoc deadline semantics cho register/payment/auto fallback
* Chot duoc MVP va out-of-scope ro rang truoc khi implement

## MVP Simplification

* MVP chi can 1 workflow `retake case` de di qua 5 buoc nghiep vu chinh, khong mo them app/phong ban/module moi neu co the tan dung man hinh list/filter va entity san co.
* Khong lam inbox/task queue rieng theo role trong MVP; moi role chi can man hinh danh sach + bo loc trang thai.
* Nhanh `Hoc lai` trong MVP chi doi status va luu audit, khong tu tao flow hoc lai tiep theo.
* Notification cho SV chi can `in-app/portal + email`.
* Manual score chi dung khi Canvas khong sync duoc; dao tao chi approve/reject, khong sua diem.
* Lop thi lai tai su dung `course_offering`/`class_sessions`; dao tao tao session theo batch va gan tay nguoi phu trach.

## Out of Scope (explicit)

* Chua implement code
* Chua sua docs repo core
* Chua cover EGC block retake discount workflow hien co
* Chua cover phuc khao/appeal diem sau thi lai neu do la quy trinh rieng
* Chua cover batch migration/backfill du lieu lich su

## Technical Approach

Da chot: dung **retake workflow entity rieng** (vi du `course_retake_cases` / `retake_exam_requests`) lam source of truth nghiep vu, thay vi nhoi toan bo state vao `course_registrations`.

Ly do:

* Buoc nghiep vu dai va nhieu role: GV -> Dao tao -> SV -> Finance -> Dao tao -> Giang vien/Canvas.
* `course_registrations` hien hop cho enrollment, nhung khong hop de mang toan bo approval/payment/scheduling lifecycle truoc khi thi lai thuc su xay ra.
* Finance va portal da co API theo charge/request; de gan vao mot case workflow se de audit, deadline, notification va fallback "Hoc lai" hon.
* Buoc 4 da chot tao `course_offering` rieng cho thi lai de tai su dung calendar, attendance, lecturer assignment, Canvas mapping, va grade input/sync.
* Buoc 3 da chot finance review theo huong prefill `unit.retake_fee`, cho phep HQ override `amount` va `due date` theo case.
* Buoc overdue unpaid da chot theo huong daily job + nut manual cho staff.
* GPA da chot theo huong lay diem cao nhat giua cac attempt cua cung 1 mon.
* Prerequisite/graduation/status da chot theo huong chi can co 1 attempt pass la dat mon.
* Buoc nominate da chot theo huong hybrid: hard-rule loc case khong hop le, GV quyet dinh tren tap con lai.
* B2 da chot tach 2 moc: `training_approved_eligibility` va `registration_opened`.
* Registration window da chot theo huong mo theo dot cua mon/lop thi lai, khong mo rieng tung case.
* Dot thi lai da chot theo huong gom nhieu case cung `unit + campus + term` vao 1 batch chung.
* `Retake batch` da chot theo huong system de xuat theo `unit + campus + term`, dao tao xac nhan/mo batch.
* Dieu kien mo `retake batch` da chot theo huong de xuat som, dao tao mo khi thay du dieu kien hoac override thu cong.
* Moc tao phi da chot theo huong SV dang ky truoc, HQ review tung case roi moi tao charge.
* Huy/rut dang ky da chot theo huong SV khong duoc tu huy; chi staff moi xu ly.
* Cancellation da chot theo huong chi huy theo flow thuong truoc khi `paid`; sau `paid` khong huy theo flow thuong.
* Sau khi case paid, case vao waiting list; he thong de xuat danh sach du thi va dao tao xac nhan cuoi.
* Roster da chot theo huong dao tao xac nhan danh sach xong roi moi tao `course_offering` thi lai.
* Nguon diem da chot theo huong Canvas la primary, manual chi fallback.
* Muc do chi tiet cua diem thi lai da chot theo huong 1 `final retake score` cho attempt moi.
* Rule Pass/Fail cua diem thi lai da chot theo huong dung lai grade scale/passing rule hien tai cua `unit`.
* Hard-rule eligibility da chot theo huong ho tro rule theo `unit` hoac theo chuong trinh/campus.
* Precedence cua hard-rule eligibility da chot theo huong `unit` override rule cap cao hon.
* `No-show` da chot theo huong van tao attempt moi va chot `final fail/no-show`.
* Sau `final fail/no-show` da chot theo huong tu dong chuyen sang `Hoc lai`.
* Nguoi phu trach `course_offering` thi lai da chot theo huong dao tao chon tay.
* Cau truc session da chot theo huong tuy batch co the co nhieu session, nhung moi student chi nam o 1 session duy nhat.
* Phan student vao session da chot theo huong system de xuat, dao tao xac nhan/chinh tay.
* Session capacity da chot theo huong dao tao nhap tay tung session.
* Attendance da chot theo huong chi check-in/diem danh o `exam` session.
* Manual fallback score da chot theo huong giang vien/can bo phu trach nhap, dao tao review.
* Review cua dao tao voi manual fallback score da chot theo huong bat buoc approve roi diem moi co hieu luc.
* Quyen review cua dao tao da chot theo huong chi `approve/reject`, khong sua diem.
* Notification toi SV da chot theo huong `in-app/portal + email`.
* Moc notification bat buoc da chot gom: mo dang ky, charge duoc tao, sap het han/thanh toan qua han, lich thi/session duoc xep, ket qua cuoi cung.
* So lan retake da chot theo huong toi da N lan, cau hinh theo rule hoc vu.
* Moc tinh da "su dung" 1 lan retake da chot theo huong chi tinh khi da co attempt thuc te voi `final pass/fail/no-show`.
* Pending actions cua staff da chot theo huong chi can man hinh danh sach + bo loc theo trang thai, khong lam inbox rieng.
* `Registration window` va `batch close` da chot theo huong dao tao nhap tay khi mo batch.
* Deadline da chot theo huong `payment deadline` tach rieng sau khi tao charge, nhung khong muon hon han chot batch.
* Charge creation da chot theo huong HQ chi tao charge trong `registration window`.
* Neu het `registration window` ma chua co charge da chot theo huong tu dong chuyen `Hoc lai`.
* Neu dao tao reject de cu thi case dong luon, khong co luong resubmit thuong.
* Neu attempt fail goc bi sua thanh `pass` trong luc case dang mo thi retake case tu dong dong.
* Neu case auto dong do diem goc sua thanh `pass` sau khi da co charge/paid thi workflow nay khong xu ly tai chinh, staff xu ly tay.
* Neu manual fallback score bi reject thi tra ve cho giang vien/can bo phu trach nhap lai.
* Neu da mo dang ky ma SV khong dang ky truoc khi het window thi case tu dong chuyen `Hoc lai`.
* Khi case da roi sang nhanh `Hoc lai` thi flow retake cho lan fail do ket thuc, khong mo lai trong workflow thuong.
* Nhanh `Hoc lai` o MVP da chot theo huong chi doi status, khong tu tao workflow hoc lai tiep theo.
* Auto-transition sang `Hoc lai` da chot theo huong bat buoc luu `reason code` system-defined.
* Thao tac manual cua staff da chot theo huong bat buoc nhap ca `reason code` va `comment`.

## Decision (ADR-lite)

**Context**: Can chon source of truth cho workflow thi lai, trong khi repo da co retake flags cu tren `course_registrations` va `academic_records`, nhung chua co lifecycle approval/payment/scheduling.

**Decision**: Tao workflow entity rieng cho retake exam; chi tao/gan registration, charge, scheduling, score sync vao entity nay qua cac moc nghiep vu.

**Consequences**:

* Pro: state machine ro, audit ro, khop business flow moi, de xu ly deadline + auto fallback.
* Con: them bang/model/action/query/UI moi; can mapping ro voi academic record va finance charge.
* Follow-up: da chot transcript theo huong add-attempt, giu attempt fail cu.
* Follow-up: da chot scheduling theo huong tao `course_offering` rieng cho thi lai.

## Transcript Rule

Da chot:

* Sau khi thi lai, tao attempt moi trong hoc tap, khong overwrite attempt fail cu.
* Lich su fail ban dau phai con de phuc vu transcript, audit, reporting, prerequisite.
* He thong can them quy tac xac dinh attempt nao la "ket qua cuoi cung" de hien thi o summary/GPA/report.

## GPA Rule

Da chot:

* GPA lay diem cao nhat giua cac attempt cua cung 1 mon.
* He thong khong duoc de tat ca attempt cung dong gop vao GPA nhu baseline hien tai neu cung 1 unit da hoc lai nhieu lan.
* Can co co che danh dau/resolve attempt nao la GPA-contributing record cho moi `student + unit`.

## Pass Rule

Da chot:

* Mon duoc xem la dat neu ton tai it nhat 1 attempt pass hop le.
* Rule nay ap dung cho prerequisite, graduation, va cac summary/trang thai hoc vu.
* Rule GPA van tach rieng: GPA lay diem cao nhat, khong nhat thiet trung voi attempt moi nhat.

## Research Notes

### Existing assets co the tai su dung

* Fail list/report: `FailedStudentsController`, `FailedStudentsService`, `FailedStudentResource`
* Complete course: `MarkCourseOfferingCompletedAction`, `CourseCompletionService`
* Finance charge + DNG: `FinanceChargeService`, `DngPaymentController`, `StudentFinanceController`
* Notification outbox: `PublishDomainEventAction`, event `academic.course_completed`
* Calendar API: `CalendarService`
* Canvas grade sync: `CanvasSyllabusController`, `CanvasGradeSyncService`
* Scheduling primitives: `CourseOffering` + `ClassSession`, trong do `ClassSession.session_type` da support `exam`

### Gaps hien tai

* Chua co entity/workflow cho retake exam approval lifecycle
* Chua co portal endpoint dang ky thi lai
* Chua co auto-job doi trang thai retake-case sang hoc lai theo deadline
* Chua co implement runtime cho quy tac transcript/attempt moi sau thi lai
* Chua co implement runtime cho quy tac GPA = highest attempt; code hien tai mac dinh dua tat ca records `excluded_from_gpa = false` vao GPA
* Chua co implement runtime cho quy tac "co 1 attempt pass la dat mon" trong prerequisite/graduation/status
* Chua co retake eligibility policy ro rang de hard-filter cac case truoc khi GV nominate
* Chua co entity/model ro rang cho `retake batch` gom nhieu case theo `unit + campus + term`
* `CourseRetakeService` baseline cu co dau hieu schema drift, khong nen xem la source of truth hien tai
* Chua co entity "ca thi lai" rieng, nhung da chot tai su dung `CourseOffering` + `ClassSession`

### Feasible approaches here

**Approach A: Retake case workflow rieng** (Recommended)

* Cac bang/workflow rieng cho approval/payment/scheduling/result.
* Chi khi can moi tao lien ket sang charge, DNG request, exam offering, score source.
* Hop nhat voi flow business nhieu role + nhieu deadline.
* User da chot approach nay.

**Approach B: Tai su dung `course_registrations` lam workflow chinh**

* Convert som sang `is_retake = true`, dung registration status + paid flags + notes de lai state.
* Nhanh hon luc dau nhung de bi roi approval/payment/scheduling vao mot bang enrollment.

**Approach C: Dua approval vao `student_actions`/`student_decisions`, enrollment/finance vao bang cu**

* Tot neu truong muon gan quyet dinh hanh chinh/van ban.
* Van can them operational state o noi khac, nen khong du de lam source of truth mot minh.

## Technical Notes

* Task dir: `.trellis/tasks/04-23-retake-exam-fee-and-regrading-flow`
* Files da inspect:
* `README.md`
* `CLAUDE.md`
* `docs/codebase-summary.md`
* `docs/system-architecture.md`
* `app/Modules/Academic/Actions/MarkCourseOfferingCompletedAction.php`
* `app/Services/CourseCompletionService.php`
* `app/Http/Controllers/Web/FailedStudentsController.php`
* `app/Services/FailedStudentsService.php`
* `app/Http/Resources/FailedStudentResource.php`
* `app/Models/AcademicRecord.php`
* `app/Models/CourseRegistration.php`
* `app/Models/FinanceCharge.php`
* `app/Modules/Finance/Services/FinanceChargeService.php`
* `app/Modules/Finance/Http/Api/Student/StudentFinanceController.php`
* `app/Modules/Finance/Dng/Support/DngFeeTypeOptions.php`
* `app/Modules/Finance/Dng/Http/Controllers/DngPaymentController.php`
* `app/Modules/Finance/Dng/Http/Requests/CreateDngPaymentFormRequest.php`
* `app/Modules/Finance/Http/Web/Admin/PaymentController.php`
* `app/Modules/Finance/routes/web.php`
* `app/Http/Controllers/Web/Canvas/CanvasSyllabusController.php`
* `app/Services/V1/Student/CalendarService.php`
* `app/Services/CourseRetakeService.php`
* `app/Services/V1/Student/CurriculumService.php`
* `app/Models/CourseOffering.php`
* `app/Models/ClassSession.php`
* Related old plan:
* `plans/260416-1024-egc-block-retake-fee/plan.md`
