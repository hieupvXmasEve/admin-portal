# Dưới đây là hướng dẫn chi tiết để tích hợp gửi email trong quy trình tạo/sửa Course Offering bằng Laravel + Inertia.js + Vue 3, với checkbox từ FE để yêu cầu gửi email cho giảng viên. Đồng thời, khi Course Offering đã từng gửi email trước đó mà bị sửa đổi về giờ học/phòng học, hệ thống sẽ gửi email cập nhật cho giảng viên.

## Phạm vi:

- Backend (Laravel): Controller Web, routes web, logic gửi email sử dụng EmailService/EmailTemplateService có sẵn.
- Frontend (Inertia + Vue 3): Form tạo/sửa với vee-validate + zod để hợp lệ, có checkbox “Gửi email cho giảng viên”.

## I. Thiết kế luồng nghiệp vụ

1) Tạo mới Course Offering

- Admin chọn giảng viên, nhập thông tin lớp học (mã, học kỳ, lịch/giờ/ phòng, v.v.)
- Tích “Gửi email cho giảng viên”
- Submit => Controller lưu DB => nếu send_email = true, gửi email “Phân công giảng dạy”

2) Cập nhật Course Offering

- Nếu đã từng gửi email (xác định qua EmailLog metadata) và lần này có đổi “giờ học” hoặc “phòng học”, hệ thống gửi
  email “Cập nhật lịch dạy” cho giảng viên.

3) Theo dõi đã gửi lần nào chưa

- Sử dụng EmailLog.metadata để lưu “entity=course_offering” + entity_id + action (“assigned” hoặc “updated”), và bản
  “hash” nhỏ của các trường quan trọng (schedule_hash) để so sánh về sau.

## II. Backend (Laravel)

1) Route web
   File: routes/web.php

- Thêm route cho tạo/sửa hiển thị form (GET) và lưu (POST/PUT)

```php
<?php
    Route::middleware(['auth', 'permissions'])->prefix('course-offerings')->name('course-offerings.')->group(function () {
    Route::get('/create', [\App\Http\Controllers\Web\CourseOfferingController::class, 'create'])->name('create');
    Route::post('/', [\App\Http\Controllers\Web\CourseOfferingController::class, 'store'])->name('store');
    Route::get('/{courseOffering}/edit', [\App\Http\Controllers\Web\CourseOfferingController::class, 'edit'])->name('edit');
    Route::put('/{courseOffering}', [\App\Http\Controllers\Web\CourseOfferingController::class, 'update'])->name('update');
});
```

2) Controller Web
   File: app/Http/Controllers/Web/CourseOfferingController.php

- Ví dụ dưới đây minh họa:
    - Nhận send_email (boolean) từ FE
    - Lưu CourseOffering
    - Tìm lecturer (User) qua lecturer_id (giả sử có quan hệ hoặc id trên form)
    - Tạo schedule_hash từ các trường quan trọng
    - Quyết định gửi email lần đầu (assigned) hoặc email cập nhật (updated)

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmailTemplate;
use App\Models\EmailLog;
use App\Services\EmailService;
use App\Services\EmailTemplateService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CourseOfferingController extends Controller
{
    public function __construct(
        protected EmailService $emailService,
        protected EmailTemplateService $templateService,
    ) {}

    public function create()
    {
        // Trả Inertia page hiển thị form
        return Inertia::render('CourseOffering/CourseOfferingForm', [
            'mode' => 'create',
            'initial' => [
                'code' => '',
                'semester' => '',
                'day_of_week' => '',
                'start_time' => '',
                'end_time' => '',
                'room' => '',
                'lecturer_id' => null,
                'send_email' => false,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        // Lưu Course Offering (ví dụ mô tả, bạn thay bằng Model thực tế)
        // giả sử có Model CourseOffering
        $courseOffering = \App\Models\CourseOffering::create([
            'code' => $data['code'],
            'semester' => $data['semester'],
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'room' => $data['room'],
            'lecturer_id' => $data['lecturer_id'],
        ]);

        // Nếu có yêu cầu gửi email
        if ($data['send_email']) {
            $this->sendAssignmentEmail($courseOffering);
        }

        return redirect()->route('course-offerings.edit', $courseOffering->id)
            ->with('success', 'Tạo course offering thành công');
    }

    public function edit(\App\Models\CourseOffering $courseOffering)
    {
        return Inertia::render('CourseOffering/CourseOfferingForm', [
            'mode' => 'edit',
            'initial' => [
                'id' => $courseOffering->id,
                'code' => $courseOffering->code,
                'semester' => $courseOffering->semester,
                'day_of_week' => $courseOffering->day_of_week,
                'start_time' => $courseOffering->start_time,
                'end_time' => $courseOffering->end_time,
                'room' => $courseOffering->room,
                'lecturer_id' => $courseOffering->lecturer_id,
                'send_email' => false, // mặc định không tick lại
            ],
        ]);
    }

    public function update(Request $request, \App\Models\CourseOffering $courseOffering)
    {
        $data = $this->validateData($request);

        $oldHash = $this->scheduleHash($courseOffering);
        $courseOffering->update([
            'code' => $data['code'],
            'semester' => $data['semester'],
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'room' => $data['room'],
            'lecturer_id' => $data['lecturer_id'],
        ]);
        $newHash = $this->scheduleHash($courseOffering);

        // Nếu được yêu cầu gửi email
        if ($data['send_email']) {
            // Kiểm tra đã từng gửi assignment email trước đó chưa
            $hasSentBefore = EmailLog::query()
                ->where('recipient', $courseOffering->lecturer?->email)
                ->where('metadata->entity', 'course_offering')
                ->where('metadata->entity_id', $courseOffering->id)
                ->where('metadata->action', 'assigned')
                ->exists();

            if (!$hasSentBefore) {
                // Chưa gửi bao giờ -> gửi mail phân công
                $this->sendAssignmentEmail($courseOffering);
            } else {
                // Đã gửi trước đó: nếu lịch/room thay đổi thì gửi mail cập nhật
                if ($oldHash !== $newHash) {
                    $this->sendUpdateEmail($courseOffering, $oldHash, $newHash);
                }
            }
        }

        return redirect()->back()->with('success', 'Cập nhật course offering thành công');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'code' => 'required|string|max:50',
            'semester' => 'required|string|max:50',
            'day_of_week' => 'required|string|max:20',
            'start_time' => 'required|string|max:10', // Hoặc format time
            'end_time' => 'required|string|max:10',
            'room' => 'required|string|max:50',
            'lecturer_id' => 'required|exists:users,id',
            'send_email' => 'boolean',
        ]);
    }

    protected function scheduleHash(\App\Models\CourseOffering $co): string
    {
        $payload = implode('|', [
            $co->day_of_week,
            $co->start_time,
            $co->end_time,
            $co->room,
        ]);

        return hash('sha256', $payload);
    }

    protected function sendAssignmentEmail(\App\Models\CourseOffering $co): void
    {
        $lecturer = $co->lecturer ?? User::find($co->lecturer_id);
        if (!$lecturer || !$lecturer->email) {
            return;
        }

        // Thử lấy template phù hợp, nếu không có thì gửi nội dung thuần
        $template = EmailTemplate::getLatestVersion('custom'); // hoặc tạo template chuyên biệt như 'course_offering_assigned'
        $variables = [
            'lecturer_name' => $lecturer->name,
            'course_code' => $co->code,
            'semester' => $co->semester,
            'day_of_week' => $co->day_of_week,
            'start_time' => $co->start_time,
            'end_time' => $co->end_time,
            'room' => $co->room,
            'system_name' => config('app.name'),
            'login_url' => config('app.url') . '/login',
        ];

        $subject = "Phân công giảng dạy: {$co->code} - {$co->semester}";
        $html = "<p>Chào {$lecturer->name},</p>
<p>Anh/chị được phân công giảng dạy lớp <strong>{$co->code}</strong> - {$co->semester}.</p>
<ul>
  <li>Thứ/Buổi: {$co->day_of_week}</li>
  <li>Giờ: {$co->start_time} - {$co->end_time}</li>
  <li>Phòng: {$co->room}</li>
</ul>
<p>Vui lòng đăng nhập cổng giảng viên để xem chi tiết: <a href=\"".e(config('app.url'))."\">".e(config('app.url'))."</a></p>";

        // Nếu có template thì render
        if ($template) {
            try {
                $rendered = $this->templateService->renderTemplate($template, array_merge($variables, [
                    'title' => $subject,
                    'body' => strip_tags($html),
                    'user_name' => $lecturer->name,
                ]));
                $subject = $rendered['subject'] ?: $subject;
                $html = $rendered['html'] ?: $html;
            } catch (\Throwable $e) {
                // fallback qua html tự soạn nếu template thiếu biến
            }
        }

        $log = $this->emailService->sendSingleEmail(
            recipient: $lecturer->email,
            subject: $subject,
            content: $html,
            template: $template,
            attachments: [],
            sender: auth()->user()
        );

        // Gán metadata để theo dõi
        $log->update([
            'metadata' => array_merge(($log->metadata ?? []), [
                'entity' => 'course_offering',
                'entity_id' => $co->id,
                'action' => 'assigned',
                'schedule_hash' => $this->scheduleHash($co),
            ])
        ]);
    }

    protected function sendUpdateEmail(\App\Models\CourseOffering $co, string $oldHash, string $newHash): void
    {
        $lecturer = $co->lecturer ?? User::find($co->lecturer_id);
        if (!$lecturer || !$lecturer->email) {
            return;
        }

        $template = EmailTemplate::getLatestVersion('custom'); // hoặc 'course_offering_updated'
        $subject = "Cập nhật lịch dạy: {$co->code} - {$co->semester}";
        $html = "<p>Chào {$lecturer->name},</p>
<p>Lịch giảng dạy của lớp <strong>{$co->code}</strong> - {$co->semester} đã được <strong>cập nhật</strong>.</p>
<ul>
  <li>Thứ/Buổi: {$co->day_of_week}</li>
  <li>Giờ: {$co->start_time} - {$co->end_time}</li>
  <li>Phòng: {$co->room}</li>
</ul>
<p>Vui lòng kiểm tra lại lịch trên hệ thống: <a href=\"".e(config('app.url'))."\">".e(config('app.url'))."</a></p>";

        if ($template) {
            try {
                $rendered = $this->templateService->renderTemplate($template, [
                    'title' => $subject,
                    'user_name' => $lecturer->name,
                    'course_code' => $co->code,
                    'semester' => $co->semester,
                    'day_of_week' => $co->day_of_week,
                    'start_time' => $co->start_time,
                    'end_time' => $co->end_time,
                    'room' => $co->room,
                    'system_name' => config('app.name'),
                ]);
                $subject = $rendered['subject'] ?: $subject;
                $html = $rendered['html'] ?: $html;
            } catch (\Throwable $e) {
                // fallback
            }
        }

        $log = $this->emailService->sendSingleEmail(
            recipient: $lecturer->email,
            subject: $subject,
            content: $html,
            template: $template,
            attachments: [],
            sender: auth()->user()
        );

        $log->update([
            'metadata' => array_merge(($log->metadata ?? []), [
                'entity' => 'course_offering',
                'entity_id' => $co->id,
                'action' => 'updated',
                'schedule_hash_old' => $oldHash,
                'schedule_hash_new' => $newHash,
            ])
        ]);
    }
}
```

Ghi chú:

- Ví dụ này sử dụng EmailLog (đã có schema) để theo dõi “đã gửi lần nào chưa” và khác biệt lịch thông qua hash. Bạn có
  thể thay bằng bảng riêng nếu muốn.
- Bạn có thể tạo 2 template chuyên dụng: course_offering_assigned và course_offering_updated để nội dung thống nhất, nếu
  không sẽ fallback nội dung HTML thuần trong Controller.

3) Model CourseOffering (tham khảo)

- Không bắt buộc chỉnh, nhưng nên có quan hệ:

```php
<?php
public function lecturer()
{
    return $this->belongsTo(\App\Models\User::class, 'lecturer_id');
}
```

## III. Frontend (Inertia + Vue 3)

1) Route Inertia

- Controller phía trên render trang resources/js/Pages/CourseOffering/CourseOfferingForm.vue

2) Form Vue 3 với vee-validate + zod
   File: resources/js/Pages/CourseOffering/CourseOfferingForm.vue

- Demo đơn giản phù hợp stack của dự án (Vue 3 + TS, vee-validate + zod)
```vue
<script setup lang="ts">
import { useForm } from 'vee-validate'
import { z } from 'zod'
import { toTypedSchema } from '@vee-validate/zod'
import { router, usePage } from '@inertiajs/vue3'

const props = defineProps<{
  mode: 'create' | 'edit'
  initial: {
    id?: number
    code: string
    semester: string
    day_of_week: string
    start_time: string
    end_time: string
    room: string
    lecturer_id: number | null
    send_email: boolean
  }
}>()

const schema = toTypedSchema(z.object({
  code: z.string().min(1).max(50),
  semester: z.string().min(1).max(50),
  day_of_week: z.string().min(1).max(20),
  start_time: z.string().min(1).max(10),
  end_time: z.string().min(1).max(10),
  room: z.string().min(1).max(50),
  lecturer_id: z.number(),
  send_email: z.boolean().optional().default(false),
}))

const { handleSubmit, defineField, errors, resetForm } = useForm({
  validationSchema: schema,
  initialValues: props.initial,
})

const [code, codeAttrs] = defineField('code')
const [semester, semesterAttrs] = defineField('semester')
const [day_of_week, dayOfWeekAttrs] = defineField('day_of_week')
const [start_time, startTimeAttrs] = defineField('start_time')
const [end_time, endTimeAttrs] = defineField('end_time')
const [room, roomAttrs] = defineField('room')
const [lecturer_id, lecturerIdAttrs] = defineField('lecturer_id')
const [send_email, sendEmailAttrs] = defineField('send_email')

const submit = handleSubmit((values) => {
  if (props.mode === 'create') {
    router.post('/course-offerings', values);
  } else {
    router.put(`/course-offerings/${props.initial.id}`, values, {
      preserveScroll: true,
    });
  }
});
</script>

<template>
  <div class="max-w-3xl mx-auto space-y-6">
    <h1 class="text-xl font-semibold">
      {{ mode === 'create' ? 'Tạo Course Offering' : 'Sửa Course Offering' }}
    </h1>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label class="block text-sm font-medium">Mã lớp</label>
        <input v-model="code" v-bind="codeAttrs" class="input" />
        <p class="text-red-600 text-sm">{{ errors.code }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium">Học kỳ</label>
        <input v-model="semester" v-bind="semesterAttrs" class="input" />
        <p class="text-red-600 text-sm">{{ errors.semester }}</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium">Thứ/Buổi</label>
          <input v-model="day_of_week" v-bind="dayOfWeekAttrs" class="input" />
          <p class="text-red-600 text-sm">{{ errors.day_of_week }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium">Phòng</label>
          <input v-model="room" v-bind="roomAttrs" class="input" />
          <p class="text-red-600 text-sm">{{ errors.room }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium">Giờ bắt đầu</label>
          <input v-model="start_time" v-bind="startTimeAttrs" class="input" />
          <p class="text-red-600 text-sm">{{ errors.start_time }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium">Giờ kết thúc</label>
          <input v-model="end_time" v-bind="endTimeAttrs" class="input" />
          <p class="text-red-600 text-sm">{{ errors.end_time }}</p>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium">Giảng viên (ID)</label>
        <input type="number" v-model.number="lecturer_id" v-bind="lecturerIdAttrs" class="input" />
        <p class="text-red-600 text-sm">{{ errors.lecturer_id }}</p>
      </div>

      <div class="flex items-center space-x-2">
        <input type="checkbox" v-model="send_email" v-bind="sendEmailAttrs" />
        <label>Gửi email cho giảng viên</label>
      </div>

      <div class="pt-4">
        <button type="submit" class="btn btn-primary">
          {{ mode === 'create' ? 'Tạo' : 'Lưu thay đổi' }}
        </button>
      </div>
    </form>

  </div>
</template>

<style scoped>
.input { @apply w-full border rounded px-3 py-2; }
.btn { @apply inline-flex items-center px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700; }
.btn-primary { }
</style>
```
Ghi chú:

- Theo sở thích dự án: sử dụng vee-validate + zod cho validation FE (đúng theo rule người dùng).
- Nếu bạn có Select component và danh sách giảng viên từ API, thay input lecturer_id bằng select.

## IV. Gợi ý Template Email
Bạn nên tạo 2 template để dùng nhất quán:

- course_offering_assigned
    - Subject: “Phân công giảng dạy: {{course_code}} - {{semester}}”
    - Biến: lecturer_name, course_code, semester, day_of_week, start_time, end_time, room, system_name, login_url
- course_offering_updated
    - Subject: “Cập nhật lịch dạy: {{course_code}} - {{semester}}”
    - Biến tương tự trên

Khi đã có template, thay EmailTemplate::getLatestVersion('custom') thành EmailTemplate::getLatestVersion('
course_offering_assigned') hoặc 'course_offering_updated'.

## V. Kiểm thử nhanh

- Bật queue worker:
  php artisan queue:work --queue=emails,bulk-emails,notifications
- Đảm bảo có cấu hình SMTP active.
- Tạo Course Offering, tick “Gửi email cho giảng viên” => nhận email phân công.
- Sửa Course Offering (đổi giờ/phòng), tick “Gửi email cho giảng viên” => nhận email cập nhật.

## VI. Mở rộng và lưu ý

- Nếu muốn kiểm soát “đã từng gửi” chắc chắn hơn, có thể lưu cờ notified_at hoặc schedule_hash vào bảng
  course_offerings, hoặc bảng bridge course_offering_notifications. Ở đây dùng EmailLog.metadata để tránh thay đổi
  schema.
- Có thể đẩy logic gửi mail sang Job riêng nếu muốn tách biệt thêm, nhưng EmailService hiện đã queue nội bộ qua
  SendSingleEmailJob.
- Đảm bảo quyền hạn: chỉ admin/staff mới được gửi mail tự động (middleware/permissions).

## Suggestion
- Tạo 2 Email Template chuyên dụng kèm Seeder.
- Tạo sẵn SFC dùng shadcn/vue hoặc Reka UI theo chuẩn giao diện dự án.
- Viết test (Feature + Unit) cho Controller và các nhánh điều kiện gửi email.
