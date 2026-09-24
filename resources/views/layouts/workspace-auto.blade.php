{{--
  SỬA 24/9 (khách: "giáo viên, phụ huynh, admin bấm Lịch sử làm bài ở trang Luyện tập công khai
  thì tự nhiên nhảy vào khu học tập của học sinh").

  KHU LÀM VIỆC TỰ NHẬN THEO VAI TRÒ, cho những trang DÙNG CHUNG cho mọi vai trò.

  Vì sao có file này: 4 file layouts/{admin,teacher,student,parent}.blade.php mỗi file gán CỨNG
  một $wsRole. Đúng với trang riêng của từng khu, nhưng SAI với các trang dùng chung — Luyện
  tập, Lịch sử làm bài, Kết quả bài làm, Quyền học, Đánh giá của tôi. Mấy trang đó nằm ở nhóm
  route KHÔNG chặn vai trò (nhóm prefix 'student' không có middleware role, và các nhóm
  'reviews.' / 'access.'), nên ai đăng nhập cũng mở được — nhưng vì chúng gán cứng lớp áo học
  sinh nên giáo viên/phụ huynh/quản trị mở ra là thấy thanh bên của HỌC SINH, cứ như vừa bị đá
  sang khu khác.

  CỐ Ý viết thành MỘT biểu thức ngay trên @extends, không tách ra @php ở trên: thứ tự chạy giữa
  @php và @extends là chuyện nội bộ của trình biên dịch Blade, mà nếu nó không như mình tưởng
  thì $wsRole rỗng và layouts/workspace lặng lẽ rơi về 'student' — tức là bug quay lại y như cũ
  mà không ai thấy. Một dòng thì không có chỗ cho thứ tự sai.

  Cách dùng: @extends('layouts.workspace-auto') — y hệt các layout khu khác, không truyền gì.
--}}
@extends('layouts.workspace', ['wsRole' => \App\Services\DashboardRoutingService::workspaceRoleFor(auth()->user())])
