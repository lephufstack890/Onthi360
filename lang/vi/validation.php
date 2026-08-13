<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines (Tiếng Việt)
    |--------------------------------------------------------------------------
    |
    | Trước đây thư mục lang/ không tồn tại trong project (giống bug config/ đã gặp) —
    | khiến Laravel không tìm thấy file dịch, và mọi lỗi validate() hiện ra dưới dạng
    | KHÓA DỊCH THÔ như "validation.integer" thay vì câu thông báo thật. File này khôi
    | phục bản dịch tiếng Việt để lỗi hiển thị đúng ngôn ngữ với phần còn lại của giao diện.
    |
    */

    'accepted' => ':attribute phải được chấp nhận.',
    'accepted_if' => ':attribute phải được chấp nhận khi :other là :value.',
    'active_url' => ':attribute không phải là URL hợp lệ.',
    'after' => ':attribute phải là ngày sau :date.',
    'after_or_equal' => ':attribute phải là ngày sau hoặc bằng :date.',
    'alpha' => ':attribute chỉ được chứa chữ cái.',
    'alpha_dash' => ':attribute chỉ được chứa chữ cái, số, gạch ngang và gạch dưới.',
    'alpha_num' => ':attribute chỉ được chứa chữ cái và số.',
    'array' => ':attribute phải là một mảng.',
    'ascii' => ':attribute chỉ được chứa ký tự và ký hiệu một byte.',
    'before' => ':attribute phải là ngày trước :date.',
    'before_or_equal' => ':attribute phải là ngày trước hoặc bằng :date.',
    'between' => [
        'array' => ':attribute phải có từ :min đến :max phần tử.',
        'file' => ':attribute phải có dung lượng từ :min đến :max kilobytes.',
        'numeric' => ':attribute phải nằm trong khoảng :min đến :max.',
        'string' => ':attribute phải có độ dài từ :min đến :max ký tự.',
    ],
    'boolean' => ':attribute chỉ được nhận giá trị đúng hoặc sai.',
    'confirmed' => ':attribute xác nhận không khớp.',
    'current_password' => 'Mật khẩu không đúng.',
    'date' => ':attribute không phải là ngày hợp lệ.',
    'date_equals' => ':attribute phải là ngày bằng :date.',
    'date_format' => ':attribute không đúng định dạng :format.',
    'decimal' => ':attribute phải có :decimal chữ số thập phân.',
    'declined' => ':attribute phải bị từ chối.',
    'declined_if' => ':attribute phải bị từ chối khi :other là :value.',
    'different' => ':attribute và :other phải khác nhau.',
    'digits' => ':attribute phải có :digits chữ số.',
    'digits_between' => ':attribute phải có từ :min đến :max chữ số.',
    'dimensions' => ':attribute có kích thước ảnh không hợp lệ.',
    'distinct' => ':attribute có giá trị trùng lặp.',
    'doesnt_end_with' => ':attribute không được kết thúc bằng một trong các giá trị: :values.',
    'doesnt_start_with' => ':attribute không được bắt đầu bằng một trong các giá trị: :values.',
    'email' => ':attribute phải là địa chỉ email hợp lệ.',
    'ends_with' => ':attribute phải kết thúc bằng một trong các giá trị: :values.',
    'enum' => ':attribute không hợp lệ.',
    'exists' => ':attribute không tồn tại.',
    'extensions' => ':attribute phải có một trong các phần mở rộng: :values.',
    'file' => ':attribute phải là một tệp.',
    'filled' => ':attribute không được để trống.',
    'gt' => [
        'array' => ':attribute phải có nhiều hơn :value phần tử.',
        'file' => ':attribute phải lớn hơn :value kilobytes.',
        'numeric' => ':attribute phải lớn hơn :value.',
        'string' => ':attribute phải dài hơn :value ký tự.',
    ],
    'gte' => [
        'array' => ':attribute phải có :value phần tử trở lên.',
        'file' => ':attribute phải lớn hơn hoặc bằng :value kilobytes.',
        'numeric' => ':attribute phải lớn hơn hoặc bằng :value.',
        'string' => ':attribute phải dài hơn hoặc bằng :value ký tự.',
    ],
    'hex_color' => ':attribute phải là mã màu hex hợp lệ.',
    'image' => ':attribute phải là một hình ảnh.',
    'in' => ':attribute không hợp lệ.',
    'in_array' => ':attribute không tồn tại trong :other.',
    'integer' => ':attribute phải là số nguyên.',
    'ip' => ':attribute phải là địa chỉ IP hợp lệ.',
    'ipv4' => ':attribute phải là địa chỉ IPv4 hợp lệ.',
    'ipv6' => ':attribute phải là địa chỉ IPv6 hợp lệ.',
    'json' => ':attribute phải là chuỗi JSON hợp lệ.',
    'list' => ':attribute phải là một danh sách.',
    'lowercase' => ':attribute phải là chữ thường.',
    'lt' => [
        'array' => ':attribute phải có ít hơn :value phần tử.',
        'file' => ':attribute phải nhỏ hơn :value kilobytes.',
        'numeric' => ':attribute phải nhỏ hơn :value.',
        'string' => ':attribute phải ngắn hơn :value ký tự.',
    ],
    'lte' => [
        'array' => ':attribute không được nhiều hơn :value phần tử.',
        'file' => ':attribute phải nhỏ hơn hoặc bằng :value kilobytes.',
        'numeric' => ':attribute phải nhỏ hơn hoặc bằng :value.',
        'string' => ':attribute phải ngắn hơn hoặc bằng :value ký tự.',
    ],
    'mac_address' => ':attribute phải là địa chỉ MAC hợp lệ.',
    'max' => [
        'array' => ':attribute không được nhiều hơn :max phần tử.',
        'file' => ':attribute không được lớn hơn :max kilobytes.',
        'numeric' => ':attribute không được lớn hơn :max.',
        'string' => ':attribute không được dài hơn :max ký tự.',
    ],
    'max_digits' => ':attribute không được nhiều hơn :max chữ số.',
    'mimes' => ':attribute phải là tệp có định dạng: :values.',
    'mimetypes' => ':attribute phải là tệp có định dạng: :values.',
    'min' => [
        'array' => ':attribute phải có ít nhất :min phần tử.',
        'file' => ':attribute phải lớn hơn hoặc bằng :min kilobytes.',
        'numeric' => ':attribute phải lớn hơn hoặc bằng :min.',
        'string' => ':attribute phải dài ít nhất :min ký tự.',
    ],
    'min_digits' => ':attribute phải có ít nhất :min chữ số.',
    'missing' => ':attribute phải không được có mặt.',
    'missing_if' => ':attribute phải không được có mặt khi :other là :value.',
    'missing_unless' => ':attribute phải không được có mặt trừ khi :other là :value.',
    'missing_with' => ':attribute phải không được có mặt khi có :values.',
    'missing_with_all' => ':attribute phải không được có mặt khi có tất cả :values.',
    'multiple_of' => ':attribute phải là bội số của :value.',
    'not_in' => ':attribute không hợp lệ.',
    'not_regex' => ':attribute có định dạng không hợp lệ.',
    'numeric' => ':attribute phải là một số.',
    'password' => [
        'letters' => ':attribute phải chứa ít nhất một chữ cái.',
        'mixed' => ':attribute phải chứa ít nhất một chữ hoa và một chữ thường.',
        'numbers' => ':attribute phải chứa ít nhất một chữ số.',
        'symbols' => ':attribute phải chứa ít nhất một ký tự đặc biệt.',
        'uncompromised' => ':attribute đã bị lộ trong một vụ rò rỉ dữ liệu, vui lòng chọn :attribute khác.',
    ],
    'present' => ':attribute phải có mặt.',
    'present_if' => ':attribute phải có mặt khi :other là :value.',
    'present_unless' => ':attribute phải có mặt trừ khi :other là :value.',
    'present_with' => ':attribute phải có mặt khi có :values.',
    'present_with_all' => ':attribute phải có mặt khi có tất cả :values.',
    'prohibited' => ':attribute bị cấm.',
    'prohibited_if' => ':attribute bị cấm khi :other là :value.',
    'prohibited_unless' => ':attribute bị cấm trừ khi :other nằm trong :values.',
    'prohibits' => ':attribute cấm :other có mặt.',
    'regex' => ':attribute có định dạng không hợp lệ.',
    'required' => ':attribute không được để trống.',
    'required_array_keys' => ':attribute phải chứa các mục: :values.',
    'required_if' => ':attribute không được để trống khi :other là :value.',
    'required_if_accepted' => ':attribute không được để trống khi :other được chấp nhận.',
    'required_if_declined' => ':attribute không được để trống khi :other bị từ chối.',
    'required_unless' => ':attribute không được để trống trừ khi :other nằm trong :values.',
    'required_with' => ':attribute không được để trống khi có :values.',
    'required_with_all' => ':attribute không được để trống khi có tất cả :values.',
    'required_without' => ':attribute không được để trống khi không có :values.',
    'required_without_all' => ':attribute không được để trống khi không có tất cả :values.',
    'same' => ':attribute và :other phải giống nhau.',
    'size' => [
        'array' => ':attribute phải chứa :size phần tử.',
        'file' => ':attribute phải có dung lượng :size kilobytes.',
        'numeric' => ':attribute phải bằng :size.',
        'string' => ':attribute phải có độ dài :size ký tự.',
    ],
    'starts_with' => ':attribute phải bắt đầu bằng một trong các giá trị: :values.',
    'string' => ':attribute phải là một chuỗi.',
    'timezone' => ':attribute phải là múi giờ hợp lệ.',
    'unique' => ':attribute đã tồn tại.',
    'uploaded' => ':attribute tải lên thất bại.',
    'uppercase' => ':attribute phải là chữ hoa.',
    'url' => ':attribute không đúng định dạng URL.',
    'ulid' => ':attribute phải là ULID hợp lệ.',
    'uuid' => ':attribute phải là UUID hợp lệ.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        // 'field_name' => ['rule_name' => 'custom-message'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | Đổi tên field kỹ thuật (vd "starts_hour") thành nhãn tiếng Việt tự nhiên trong câu
    | thông báo lỗi (vd "Giờ bắt đầu không được để trống." thay vì "starts_hour không
    | được để trống."). Chỉ liệt kê các field đã dùng validate() thật trong app — thêm
    | dần khi phát hiện field mới thiếu nhãn.
    |
    */

    'attributes' => [
        // Lịch / buổi học (teacher.schedule.*)
        'class_room_id' => 'lớp học',
        'starts_date' => 'ngày bắt đầu',
        'starts_hour' => 'giờ bắt đầu',
        'starts_minute' => 'phút bắt đầu',
        'ends_date' => 'ngày kết thúc',
        'ends_hour' => 'giờ kết thúc',
        'ends_minute' => 'phút kết thúc',
        'topic' => 'chủ đề',
        'location' => 'địa điểm',
        'status' => 'trạng thái',

        // Chung
        'name' => 'tên',
        'title' => 'tiêu đề',
        'email' => 'email',
        'password' => 'mật khẩu',
        'code' => 'mã',
        'description' => 'mô tả',
        'note' => 'ghi chú',
        'phone' => 'số điện thoại',
        'address' => 'địa chỉ',
        'content' => 'nội dung',
        'type' => 'loại',
        'date' => 'ngày',
        'start_date' => 'ngày bắt đầu',
        'end_date' => 'ngày kết thúc',
        'starts_at' => 'thời gian bắt đầu',
        'ends_at' => 'thời gian kết thúc',
        'opens_at' => 'thời gian mở',
        'closes_at' => 'thời gian đóng',
        'due_at' => 'hạn nộp',
    ],

];
