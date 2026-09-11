{{-- Alpine cho màn Thông tin — chuyển đúng useState của
     education-main/src/components/InfoPage.jsx: mở/đóng từng câu hỏi FAQ.
     Khác bản mẫu: trạng thái "đã gửi" do MÁY CHỦ quyết định (session sau khi ghi
     contact_messages), Alpine ở đây chỉ dùng để mở lại form khi muốn gửi thêm yêu cầu. --}}
<script>
    function onthiInfoPage() {
        return {
            openFaq: null,
            resend: false,

            toggleFaq(i) { this.openFaq = this.openFaq === i ? null : i; },
        };
    }
</script>
