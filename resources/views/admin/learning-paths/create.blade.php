@extends('layouts.admin')

@section('title', 'Thêm lộ trình')
@section('page-title', 'Thêm lộ trình')

@section('content')
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    @include('admin.learning-paths.form')
@endsection
