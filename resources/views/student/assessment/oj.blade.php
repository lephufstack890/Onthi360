@extends('layouts.student')

@section('title', 'Làm bài lập trình')
@section('page-title', '')

@section('content')
    @php
        $submissions = $submissions ?? [];
        $questionTitle = $questionModel->title ?? 'Bài 12: Đệ quy cơ bản';
    @endphp

    <div class="flex items-center justify-between mb-4">
        <h1 class="font-medium text-slate-800">{{ $questionTitle }}</h1>
        <div class="flex items-center gap-2">
            <x-select icon="💻">
                <option>C++17</option>
                <option>Python 3</option>
                <option>Java 17</option>
            </x-select>
            <button type="button" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">▶ Chạy thử</button>
            <button type="button" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Nộp bài</button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Đề bài --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5 overflow-y-auto max-h-[75vh]">
            <h2 class="font-medium text-slate-700 mb-2">Đề bài</h2>
            <p class="text-sm text-slate-600 leading-relaxed mb-4">
                Viết hàm đệ quy tính giai thừa của số nguyên dương <code class="px-1 bg-slate-100 rounded">n</code>.
                In ra kết quả trên một dòng.
            </p>

            <h3 class="text-sm font-medium text-slate-700 mb-1">Input</h3>
            <p class="text-sm text-slate-500 mb-3">Một số nguyên <code class="px-1 bg-slate-100 rounded">n</code> (1 ≤ n ≤ 20).</p>

            <h3 class="text-sm font-medium text-slate-700 mb-1">Output</h3>
            <p class="text-sm text-slate-500 mb-3">Giá trị n!.</p>

            <h3 class="text-sm font-medium text-slate-700 mb-1">Ví dụ</h3>
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div class="bg-slate-50 rounded-lg p-3">
                    <p class="text-xs text-slate-400 mb-1">Input</p>
                    <pre class="text-sm font-mono text-slate-700">5</pre>
                </div>
                <div class="bg-slate-50 rounded-lg p-3">
                    <p class="text-xs text-slate-400 mb-1">Output</p>
                    <pre class="text-sm font-mono text-slate-700">120</pre>
                </div>
            </div>

            <h3 class="text-sm font-medium text-slate-700 mb-1">Ràng buộc</h3>
            <p class="text-sm text-slate-500">Time limit: 1s · Memory limit: 256MB.</p>
        </div>

        {{-- Editor + kết quả --}}
        <div class="flex flex-col gap-4">
            <div class="bg-slate-900 rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2 bg-slate-800 text-xs text-slate-400">
                    <span>main.cpp</span>
                    <span>C++17</span>
                </div>
                {{-- TODO: thay textarea bằng Monaco/CodeMirror thật --}}
                <textarea class="w-full h-64 bg-slate-900 text-slate-100 font-mono text-sm p-4 resize-none focus:outline-none" spellcheck="false">#include <bits/stdc++.h>
using namespace std;

long long factorial(int n) {
    if (n <= 1) return 1;
    return n * factorial(n - 1);
}

int main() {
    int n; cin >> n;
    cout << factorial(n) << endl;
}</textarea>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-4">
                <h3 class="text-sm font-medium text-slate-700 mb-2">Kết quả chạy thử</h3>
                <p class="text-xs text-slate-400">Nhấn "Chạy thử" để xem output với test công khai (không tính vào lịch sử nộp).</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-4">
                <h3 class="text-sm font-medium text-slate-700 mb-3">Lịch sử nộp</h3>
                <ul class="space-y-2">
                    @forelse ($submissions as $s)
                        <li class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">{{ $s['time'] }}</span>
                            <x-status-badge :tone="$s['tone']">{{ $s['verdict'] }}</x-status-badge>
                        </li>
                    @empty
                        <li class="text-sm text-slate-400">Chưa có lượt nộp nào cho câu này.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
