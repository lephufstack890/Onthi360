@extends('layouts.admin')

@section('title', 'Sửa lộ trình')
@section('page-title', 'Sửa lộ trình')

@section('content')
    @if (session('status') === 'path-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thay đổi.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    @include('admin.learning-paths.form')
@endsection
