<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use App\Services\Admin\TestimonialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Quản trị "Câu chuyện đồng hành" hiển thị ở trang chủ ([HOME-10]).
 */
class TestimonialController extends Controller
{
    public function __construct(private TestimonialService $testimonialService) {}

    public function index(): View
    {
        return view('admin.testimonials.index', $this->testimonialService->indexData());
    }

    public function create(): View
    {
        return view('admin.testimonials.create', $this->testimonialService->createFormData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $this->testimonialService->store(
            Auth::user(),
            $data,
            $request->file('avatar'),
            $request->file('banner'),
        );

        return redirect()->route('admin.testimonials.index')->with('status', 'testimonial-created');
    }

    public function edit(int $testimonial): View
    {
        return view('admin.testimonials.edit', $this->testimonialService->editFormData($testimonial));
    }

    public function update(Request $request, Testimonial $testimonial): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $this->testimonialService->update(
            $testimonial,
            $data,
            $request->file('avatar'),
            $request->file('banner'),
        );

        return redirect()->route('admin.testimonials.index')->with('status', 'testimonial-updated');
    }

    public function togglePublish(Testimonial $testimonial): RedirectResponse
    {
        $this->testimonialService->togglePublish($testimonial);

        return back()->with('status', 'testimonial-toggled');
    }

    public function destroy(Request $request, Testimonial $testimonial): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->testimonialService->destroy($testimonial, $data['reason']);

        return redirect()->route('admin.testimonials.index')->with('status', 'testimonial-deleted');
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'quote' => ['required', 'string', 'max:1000'],
            'author_name' => ['required', 'string', 'max:120'],
            'author_role' => ['nullable', 'string', 'max:120'],
            'author_org' => ['nullable', 'string', 'max:160'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'verified' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'banner' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
