@extends('layouts.admin')

@section('title', 'Thêm câu chuyện đồng hành')
@section('page-title', 'Thêm câu chuyện')

@section('content')
    @include('admin.testimonials.form', ['testimonial' => $testimonial, 'statuses' => $statuses])
@endsection
