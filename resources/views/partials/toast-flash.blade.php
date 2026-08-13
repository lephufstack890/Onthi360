{{--
  Include này thay cho banner màu inline: @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu.'])
  type: success|error|warning|info. Nhiều toast/trang: include nhiều lần, mỗi lần 1 toast.
--}}
@php $type = $type ?? 'info'; @endphp
<script>
    window.__flashToasts.push({ type: @json($type), message: @json($message) });
</script>
