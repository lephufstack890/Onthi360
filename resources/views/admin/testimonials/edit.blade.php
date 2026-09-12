@extends('layouts.admin')

@section('title', 'Sửa câu chuyện đồng hành')
@section('page-title', 'Sửa câu chuyện')

@section('content')
    @include('admin.testimonials.form', ['testimonial' => $testimonial, 'statuses' => $statuses])
@endsection
