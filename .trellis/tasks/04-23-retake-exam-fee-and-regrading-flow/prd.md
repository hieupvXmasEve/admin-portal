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

* Phi thi lai duoc tao o moc nao:
* ngay khi SV bam dang ky
* sau khi HQ review tung case dang ky
* sau khi dao tao mo/chot batch roi moi tao theo lo

## Requirements (evolving)

* Sau khi complete course, he thong phai tao/refresh danh sach fail cho course offering va cho GV de cu ung vien du dieu kien thi lai.
* Workflow thi lai phai dung `retake case`/`retake exam request` rieng lam source of truth, tach khoi `course_registrations` trong giai doan approval/payment/scheduling.
* Ket qua thi lai phai tao attempt moi va giu nguyen attempt fail cu.
* He thong phai co quy tac xac dinh ket qua cuoi cung cua mon dua tren attempt thi lai hop le moi nhat.
* GPA phai tinh theo **diem cao nhat giua cac attempt cua cung 1 mon**, khong cong chong tat ca attempt vao GPA.
* Prerequisite, graduation, va trang thai dat mon phai theo rule: chi can co it nhat 1 attempt pass la mon duoc tinh dat.
* GV phai co tieu chi va ghi nhan quyet dinh de cu thi lai tren tung sinh vien fail.
* B1 phai theo rule hybrid: he thong loai truoc cac case chac chan khong hop le theo hard rules (vi du diem danh khong du, vi pham di hoc muon qua nguong, dieu kien ky luat/nep hoc neu co), sau do GV moi quyet dinh nominate/not nominate trong tap con lai.
* Dao tao phai co inbox/list de phe duyet hoac tu choi de cu tu GV.
* B2 phai tach 2 state: dao tao approve eligibility truoc, sau do staff/dao tao mo quyen dang ky tren Portal bang mot thao tac rieng.
* Registration window phai mo theo dot cua tung mon/lop thi lai, nhieu sinh vien dung chung 1 cua so dang ky.
* Dot thi lai phai gom nhieu `retake case` cung mon trong cung `campus + term` vao 1 batch chung.
* He thong phai tu de xuat `retake batch` theo `unit + campus + term`, sau do dao tao xac nhan/mo batch thay vi tu tao hoan toan hoac staff tao tay tu dau.
* He thong duoc phep de xuat `retake batch` som, nhung batch chi mo/chinh thuc khi dao tao thay du dieu kien va xac nhan, hoac staff override thu cong.
* Chi sinh vien duoc dao tao cho phep moi thay CTA dang ky thi lai tren Portal.
* Sinh vien tu dang ky trong cua so thoi gian hop le; he thong gui/thay notification ro trang thai.
* HQ/Admin finance phai review va tao phi thi lai de sinh vien thanh toan tren Portal.
* HQ/Admin finance phai lay `unit.retake_fee` lam amount mac dinh, nhung duoc sua `amount` va `due date` tung case truoc khi tao charge/DNG request.
* Neu sinh vien khong thanh toan dung han thi workflow phai auto chuyen sang nhanh "Hoc lai".
* He thong phai co daily job de quet retake case overdue unpaid va cho staff bam nut manual de chuyen ngay neu can.
* Neu sinh vien thanh toan thanh cong thi workflow phai chuyen sang trang thai "Cho xep lich/cho thi lai".
* Dao tao phai chot danh sach du thi, tao `course_offering` rieng cho thi lai, day lich sang portal calendar, va co diem danh + nhap diem.
* Diem thi lai phai co 2 cach vao he thong: sync tu Canvas hoac nhap tay.
* He thong phai chot ket qua cuoi cung Pass/Fail tren ho so hoc tap mot cach audit duoc.
* Toan bo workflow phai campus-scoped, co permission ro cho GV, Dao tao, HQ/Admin finance.
* He thong phai co audit trail va thong bao cho cac moc trang thai quan trong.

## Acceptance Criteria (evolving)

* [ ] Co state machine ro rang cho `retake case`: fail -> lecturer_nominated -> training_approved_eligibility/training_rejected -> registration_opened -> student_registered -> charge_created -> payment_pending/paid -> scheduled -> assessed -> final_pass/final_fail -> fallback_repeat_course.
* [ ] `Retake case` la source of truth cho approval/payment/scheduling/result; `course_registrations` khong bi dung lam workflow chinh tu dau.
* [ ] Sau thi lai, he thong tao duoc attempt moi va van giu duoc record fail cu.
* [ ] GPA chi tinh tren attempt co diem cao nhat cua moi mon theo rule da chot.
* [ ] Mon duoc xem la dat cho prerequisite/graduation/status neu ton tai it nhat 1 attempt pass hop le.
* [ ] Complete Course co diem mo rong de GV thao tac danh sach fail ngay sau khi finalize.
* [ ] Danh sach fail de GV nominate da loai san cac case vi pham hard rules; GV chi thao tac tren tap con hop le va quyet dinh nominate/not nominate duoc audit.
* [ ] Dao tao phe duyet eligibility va mo quyen dang ky la 2 moc state rieng, audit duoc, khong gom chung thanh 1 thao tac.
* [ ] Dao tao co man hinh phe duyet danh sach thi lai va chot danh sach du thi.
* [ ] Portal chi hien dang ky thi lai cho sinh vien da duoc approve va dang trong registration window chung cua dot thi lai tuong ung.
* [ ] Cac `retake case` cung `unit + campus + term` co the duoc gom vao cung 1 `retake batch`/dot thi lai thay vi tach theo tung offering goc.
* [ ] He thong tu de xuat `retake batch` theo `unit + campus + term`, va batch chi duoc xem la mo/chinh thuc sau khi dao tao xac nhan.
* [ ] `Retake batch` co the ton tai o trang thai de xuat som; batch chi mo khi dao tao xac nhan du dieu kien hoac chu dong override.
* [ ] Finance tao duoc phi thi lai, portal xem duoc charge/invoice/DNG request va thanh toan duoc.
* [ ] Finance form prefill `unit.retake_fee`, nhung HQ sua duoc `amount` va `due date` tung case truoc khi tao charge.
* [ ] Qua han khong thanh toan thi case auto vao trang thai "Hoc lai" qua daily job, va staff co them nut manual transition.
* [ ] Sau khi tao `course_offering` thi lai, sinh vien thay lich tren calendar va staff co diem danh/nhap diem.
* [ ] Diem thi lai vao he thong qua Canvas sync hoac manual input, va ket qua cuoi cung audit duoc.
* [ ] Khong doan field/relationship; moi quyet dinh dua tren schema/code thuc te.

## Definition of Done (team quality bar)

* Chot duoc source of truth entity cho retake workflow
* Chot duoc quy tac cap nhat transcript/academic record sau thi lai
* Chot duoc ownership role + permission + trigger thong bao
* Chot duoc deadline semantics cho register/payment/auto fallback
* Chot duoc MVP va out-of-scope ro rang truoc khi implement

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
* Chua co workflow cu the noi `student_registered` voi moc finance charge creation
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
