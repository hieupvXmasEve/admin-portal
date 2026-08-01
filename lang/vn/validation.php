<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines (Tiếng Việt)
    |--------------------------------------------------------------------------
    |
    | APP_LOCALE của ứng dụng là `vn`. Nếu thiếu file này, Laravel không tìm
    | được bản dịch và trả về nguyên key (ví dụ "validation.required") ra tận
    | giao diện người dùng.
    |
    | `:attribute` là tên trường; đặt tên hiển thị tiếng Việt trong mảng
    | `attributes` ở cuối file khi cần.
    |
    */

    'accepted' => 'Bạn phải chấp nhận :attribute.',
    'accepted_if' => 'Bạn phải chấp nhận :attribute khi :other là :value.',
    'active_url' => ':attribute phải là một URL hợp lệ.',
    'after' => ':attribute phải là ngày sau :date.',
    'after_or_equal' => ':attribute phải là ngày bằng hoặc sau :date.',
    'alpha' => ':attribute chỉ được chứa chữ cái.',
    'alpha_dash' => ':attribute chỉ được chứa chữ cái, số, dấu gạch ngang và gạch dưới.',
    'alpha_num' => ':attribute chỉ được chứa chữ cái và số.',
    'any_of' => ':attribute không hợp lệ.',
    'array' => ':attribute phải là một danh sách.',
    'ascii' => ':attribute chỉ được chứa ký tự và ký hiệu một byte.',
    'before' => ':attribute phải là ngày trước :date.',
    'before_or_equal' => ':attribute phải là ngày bằng hoặc trước :date.',
    'between' => [
        'array' => ':attribute phải có từ :min đến :max mục.',
        'file' => ':attribute phải có dung lượng từ :min đến :max KB.',
        'numeric' => ':attribute phải nằm trong khoảng :min đến :max.',
        'string' => ':attribute phải có độ dài từ :min đến :max ký tự.',
    ],
    'boolean' => ':attribute phải là đúng hoặc sai.',
    'can' => ':attribute chứa giá trị không được phép.',
    'confirmed' => 'Xác nhận :attribute không khớp.',
    'contains' => ':attribute thiếu một giá trị bắt buộc.',
    'current_password' => 'Mật khẩu không đúng.',
    'date' => ':attribute phải là ngày hợp lệ.',
    'date_equals' => ':attribute phải là ngày bằng :date.',
    'date_format' => ':attribute phải đúng định dạng :format.',
    'decimal' => ':attribute phải có :decimal chữ số thập phân.',
    'declined' => ':attribute phải bị từ chối.',
    'declined_if' => ':attribute phải bị từ chối khi :other là :value.',
    'different' => ':attribute và :other phải khác nhau.',
    'digits' => ':attribute phải có :digits chữ số.',
    'digits_between' => ':attribute phải có từ :min đến :max chữ số.',
    'dimensions' => ':attribute có kích thước ảnh không hợp lệ.',
    'distinct' => ':attribute bị trùng giá trị.',
    'doesnt_contain' => ':attribute không được chứa bất kỳ giá trị nào sau: :values.',
    'doesnt_end_with' => ':attribute không được kết thúc bằng: :values.',
    'doesnt_start_with' => ':attribute không được bắt đầu bằng: :values.',
    'email' => ':attribute phải là địa chỉ email hợp lệ.',
    'encoding' => ':attribute phải được mã hoá theo :encoding.',
    'ends_with' => ':attribute phải kết thúc bằng một trong: :values.',
    'enum' => ':attribute đã chọn không hợp lệ.',
    'exists' => ':attribute đã chọn không tồn tại.',
    'extensions' => ':attribute phải có phần mở rộng thuộc: :values.',
    'file' => ':attribute phải là một tệp.',
    'filled' => ':attribute không được để trống.',
    'gt' => [
        'array' => ':attribute phải có nhiều hơn :value mục.',
        'file' => ':attribute phải lớn hơn :value KB.',
        'numeric' => ':attribute phải lớn hơn :value.',
        'string' => ':attribute phải dài hơn :value ký tự.',
    ],
    'gte' => [
        'array' => ':attribute phải có ít nhất :value mục.',
        'file' => ':attribute phải lớn hơn hoặc bằng :value KB.',
        'numeric' => ':attribute phải lớn hơn hoặc bằng :value.',
        'string' => ':attribute phải dài ít nhất :value ký tự.',
    ],
    'hex_color' => ':attribute phải là mã màu hex hợp lệ.',
    'image' => ':attribute phải là một ảnh.',
    'in' => ':attribute đã chọn không hợp lệ.',
    'in_array' => ':attribute phải tồn tại trong :other.',
    'in_array_keys' => ':attribute phải chứa ít nhất một trong các khoá: :values.',
    'integer' => ':attribute phải là số nguyên.',
    'ip' => ':attribute phải là địa chỉ IP hợp lệ.',
    'ipv4' => ':attribute phải là địa chỉ IPv4 hợp lệ.',
    'ipv6' => ':attribute phải là địa chỉ IPv6 hợp lệ.',
    'json' => ':attribute phải là chuỗi JSON hợp lệ.',
    'list' => ':attribute phải là một danh sách.',
    'lowercase' => ':attribute phải viết thường.',
    'lt' => [
        'array' => ':attribute phải có ít hơn :value mục.',
        'file' => ':attribute phải nhỏ hơn :value KB.',
        'numeric' => ':attribute phải nhỏ hơn :value.',
        'string' => ':attribute phải ngắn hơn :value ký tự.',
    ],
    'lte' => [
        'array' => ':attribute không được có quá :value mục.',
        'file' => ':attribute phải nhỏ hơn hoặc bằng :value KB.',
        'numeric' => ':attribute phải nhỏ hơn hoặc bằng :value.',
        'string' => ':attribute không được dài quá :value ký tự.',
    ],
    'mac_address' => ':attribute phải là địa chỉ MAC hợp lệ.',
    'max' => [
        'array' => ':attribute không được có quá :max mục.',
        'file' => ':attribute không được lớn hơn :max KB.',
        'numeric' => ':attribute không được lớn hơn :max.',
        'string' => ':attribute không được dài quá :max ký tự.',
    ],
    'max_digits' => ':attribute không được có quá :max chữ số.',
    'mimes' => ':attribute phải là tệp thuộc loại: :values.',
    'mimetypes' => ':attribute phải là tệp thuộc loại: :values.',
    'min' => [
        'array' => ':attribute phải có ít nhất :min mục.',
        'file' => ':attribute phải có dung lượng ít nhất :min KB.',
        'numeric' => ':attribute phải ít nhất là :min.',
        'string' => ':attribute phải có ít nhất :min ký tự.',
    ],
    'min_digits' => ':attribute phải có ít nhất :min chữ số.',
    'missing' => ':attribute không được xuất hiện.',
    'missing_if' => ':attribute không được xuất hiện khi :other là :value.',
    'missing_unless' => ':attribute không được xuất hiện trừ khi :other là :value.',
    'missing_with' => ':attribute không được xuất hiện khi có :values.',
    'missing_with_all' => ':attribute không được xuất hiện khi có đủ :values.',
    'multiple_of' => ':attribute phải là bội số của :value.',
    'not_in' => ':attribute đã chọn không hợp lệ.',
    'not_regex' => ':attribute có định dạng không hợp lệ.',
    'numeric' => ':attribute phải là một số.',
    'password' => [
        'letters' => ':attribute phải chứa ít nhất một chữ cái.',
        'mixed' => ':attribute phải chứa ít nhất một chữ hoa và một chữ thường.',
        'numbers' => ':attribute phải chứa ít nhất một chữ số.',
        'symbols' => ':attribute phải chứa ít nhất một ký tự đặc biệt.',
        'uncompromised' => ':attribute này đã xuất hiện trong một vụ lộ dữ liệu. Vui lòng chọn giá trị khác.',
    ],
    'present' => ':attribute phải được gửi lên.',
    'present_if' => ':attribute phải được gửi lên khi :other là :value.',
    'present_unless' => ':attribute phải được gửi lên trừ khi :other là :value.',
    'present_with' => ':attribute phải được gửi lên khi có :values.',
    'present_with_all' => ':attribute phải được gửi lên khi có đủ :values.',
    'prohibited' => ':attribute không được phép.',
    'prohibited_if' => ':attribute không được phép khi :other là :value.',
    'prohibited_if_accepted' => ':attribute không được phép khi :other được chấp nhận.',
    'prohibited_if_declined' => ':attribute không được phép khi :other bị từ chối.',
    'prohibited_unless' => ':attribute không được phép trừ khi :other thuộc :values.',
    'prohibits' => ':attribute khiến :other không được phép xuất hiện.',
    'regex' => ':attribute có định dạng không hợp lệ.',
    'required' => 'Vui lòng nhập :attribute.',
    'required_array_keys' => ':attribute phải chứa các mục: :values.',
    'required_if' => 'Vui lòng nhập :attribute khi :other là :value.',
    'required_if_accepted' => 'Vui lòng nhập :attribute khi :other được chấp nhận.',
    'required_if_declined' => 'Vui lòng nhập :attribute khi :other bị từ chối.',
    'required_unless' => 'Vui lòng nhập :attribute trừ khi :other thuộc :values.',
    'required_with' => 'Vui lòng nhập :attribute khi có :values.',
    'required_with_all' => 'Vui lòng nhập :attribute khi có đủ :values.',
    'required_without' => 'Vui lòng nhập :attribute khi không có :values.',
    'required_without_all' => 'Vui lòng nhập :attribute khi không có bất kỳ giá trị nào trong :values.',
    'same' => ':attribute phải trùng với :other.',
    'size' => [
        'array' => ':attribute phải có đúng :size mục.',
        'file' => ':attribute phải có dung lượng :size KB.',
        'numeric' => ':attribute phải bằng :size.',
        'string' => ':attribute phải có đúng :size ký tự.',
    ],
    'starts_with' => ':attribute phải bắt đầu bằng một trong: :values.',
    'string' => ':attribute phải là một chuỗi ký tự.',
    'timezone' => ':attribute phải là múi giờ hợp lệ.',
    'unique' => ':attribute đã tồn tại.',
    'uploaded' => 'Tải :attribute lên thất bại.',
    'uppercase' => ':attribute phải viết hoa.',
    'url' => ':attribute phải là một URL hợp lệ.',
    'ulid' => ':attribute phải là ULID hợp lệ.',
    'uuid' => ':attribute phải là UUID hợp lệ.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Thông báo riêng theo từng trường, đặt tên theo quy ước "trường.luật".
    | Ưu tiên đặt thông báo nghiệp vụ trong messages() của FormRequest tương
    | ứng; chỉ dùng chỗ này cho các trường dùng chung nhiều nơi.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | Tên hiển thị tiếng Việt cho :attribute, thay cho tên cột thô như
    | "student_id". Bổ sung dần khi gặp trường hiển thị chưa thân thiện.
    |
    | Lưu ý: đây là bảng DÙNG CHUNG toàn ứng dụng — một khoá như `reason` áp
    | dụng cho mọi form có trường tên đó. Chỉ đặt ở đây những tên đúng nghĩa
    | trong mọi ngữ cảnh; câu chữ riêng của một nghiệp vụ nên nằm trong
    | messages() của FormRequest tương ứng.
    |
    */

    'attributes' => [

        // Dùng chung nhiều màn hình
        'campus_id' => 'cơ sở',
        'reason' => 'lý do',
        'comment' => 'ý kiến',
        'location' => 'địa điểm',
        'mode' => 'hình thức',
        'student_code' => 'mã sinh viên',
        'student_ids' => 'danh sách sinh viên',
        'student_ids.*' => 'sinh viên được chọn',
        'source_semester_id' => 'học kỳ bị trượt môn',
        'target_semester_id' => 'học kỳ áp dụng điều chỉnh',

        // Điều chỉnh học bổng — phỏng vấn
        'scheduled_at' => 'thời gian phỏng vấn',
        'minutes' => 'biên bản phỏng vấn',
        'minutes_version' => 'phiên bản biên bản',
        'participants' => 'người tham dự',

        // Điều chỉnh học bổng — xác nhận của sinh viên
        'agree' => 'lựa chọn đồng ý hay không',
        'on_behalf_note' => 'ghi chú xác nhận thay sinh viên',
        'overrule_reason' => 'lý do bác bỏ phản đối',

        // Điều chỉnh học bổng — quyết định
        'decision_type' => 'hình thức xử lý học bổng',
        'adjusted_amount' => 'mức học bổng còn lại',
        'exception_reason' => 'lý do thêm ngoại lệ',
        'exception_override_reason' => 'lý do bỏ qua điều kiện',

    ],

];
