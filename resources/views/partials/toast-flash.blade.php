@php $type = $type ?? 'info'; @endphp
<script>
    window.__flashToasts.push({ type: @json($type), message: @json($message) });
</script>
