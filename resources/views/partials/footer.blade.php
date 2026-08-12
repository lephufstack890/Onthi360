{{-- Footer công khai: cam kết, FAQ rút gọn, liên hệ, chính sách (12.1 mục 9). --}}
<footer class="bg-white border-t border-slate-200 mt-16">
    <div class="max-w-7xl mx-auto px-4 py-10 grid grid-cols-1 md:grid-cols-4 gap-8 text-sm text-slate-500">
        <div>
            <div class="font-semibold text-rose-600 mb-2">Ôn Thi 360</div>
            <p>Học có lộ trình, luyện tập và chấm bài — đồng hành cùng học sinh, giáo viên và phụ huynh trên một nền tảng minh bạch, luôn nêu đúng lý do trước khi khóa nội dung.</p>
            <ul class="space-y-1.5 mt-4">
                <li class="flex items-center gap-2">📞 <a href="tel:0978729962">0978 729 962</a></li>
                <li class="flex items-center gap-2">✉️ <a href="mailto:support@onthi360.edu.vn">support@onthi360.edu.vn</a></li>
                <li class="flex items-center gap-2">📘 <a href="https://facebook.com/onthi360" target="_blank" rel="noopener">facebook.com/onthi360</a></li>
                <li class="flex items-center gap-2">💬 <a href="https://zalo.me/onthi360" target="_blank" rel="noopener">zalo.me/onthi360</a></li>
            </ul>
        </div>
        <div>
            <div class="font-medium text-slate-700 mb-2">Sản phẩm</div>
            <ul class="space-y-1">
                <li><a href="{{ route('courses.index') }}">Khóa học</a></li>
                <li><a href="{{ route('materials.index') }}">Tài liệu</a></li>
                <li><a href="{{ route('practice.index') }}">Luyện tập</a></li>
            </ul>
        </div>
        <div>
            <div class="font-medium text-slate-700 mb-2">Hỗ trợ</div>
            <ul class="space-y-1">
                <li><a href="{{ route('info.index') }}">FAQ</a></li>
                <li><a href="{{ route('info.index') }}">Liên hệ</a></li>
            </ul>
        </div>
        <div>
            <div class="font-medium text-slate-700 mb-2">Chính sách</div>
            <ul class="space-y-1">
                <li>{{-- TODO: link chính sách bảo mật/điều khoản --}}</li>
            </ul>
        </div>
    </div>
</footer>
