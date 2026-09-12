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
            <x-ws.select icon="💻">
                <option>C++17</option>
                <option>Python 3</option>
                <option>Java 17</option>
            </x-ws.select>
            <button type="button" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">▶ Chạy thử</button>
            <button type="button" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">Nộp bài</button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Đề bài --}}
        <div class="bg-white rounded-3xl border border-sky-100 p-5 overflow-y-auto max-h-[75vh]">
            <h2 class="font-medium text-slate-700 mb-2">Đề bài</h2>
            <p class="text-[13px] text-slate-600 leading-relaxed mb-4">
                Viết hàm đệ quy tính giai thừa của số nguyên dương <code class="px-1 bg-slate-100 rounded">n</code>.
                In ra kết quả trên một dòng.
            </p>

            <h3 class="text-[13px] font-medium text-slate-700 mb-1">Input</h3>
            <p class="text-[13px] text-slate-500 mb-3">Một số nguyên <code class="px-1 bg-slate-100 rounded">n</code> (1 ≤ n ≤ 20).</p>

            <h3 class="text-[13px] font-medium text-slate-700 mb-1">Output</h3>
            <p class="text-[13px] text-slate-500 mb-3">Giá trị n!.</p>

            <h3 class="text-[13px] font-medium text-slate-700 mb-1">Ví dụ</h3>
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div class="bg-slate-50 rounded-xl p-3">
                    <p class="text-xs text-slate-400 mb-1">Input</p>
                    <pre class="text-[13px] font-mono text-slate-700">5</pre>
                </div>
                <div class="bg-slate-50 rounded-xl p-3">
                    <p class="text-xs text-slate-400 mb-1">Output</p>
                    <pre class="text-[13px] font-mono text-slate-700">120</pre>
                </div>
            </div>

            <h3 class="text-[13px] font-medium text-slate-700 mb-1">Ràng buộc</h3>
            <p class="text-[13px] text-slate-500">Time limit: 1s · Memory limit: 256MB.</p>
        </div>

        {{-- Editor + kết quả --}}
        <div class="flex flex-col gap-4">
            <div class="bg-slate-900 rounded-3xl overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2 bg-slate-800 text-xs text-slate-400">
                    <span>main.cpp</span>
                    <span>C++17</span>
                </div>
                {{-- TODO: thay textarea bằng Monaco/CodeMirror thật --}}
                <textarea class="w-full h-64 bg-slate-900 text-slate-100 font-mono text-[13px] p-4 resize-none focus:outline-none" spellcheck="false">#include <bits/stdc++.h>
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

            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4">
                <h3 class="text-[13px] font-medium text-slate-700 mb-2">Kết quả chạy thử</h3>
                <p class="text-xs text-slate-400">Nhấn "Chạy thử" để xem output với test công khai (không tính vào lịch sử nộp).</p>
            </div>

            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4">
                <h3 class="text-[13px] font-medium text-slate-700 mb-3">Lịch sử nộp</h3>
                <ul class="space-y-2">
                    @forelse ($submissions as $s)
                        <li class="flex items-center justify-between text-[13px]">
                            <span class="text-slate-500">{{ $s['time'] }}</span>
                            <x-ws.badge :tone="$s['tone']">{{ $s['verdict'] }}</x-ws.badge>
                        </li>
                    @empty
                        <li class="text-[13px] text-slate-400">Chưa có lượt nộp nào cho câu này.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
