<?php

namespace App\Repositories\Eloquent;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Repositories\Contracts\ContactMessageRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ContactMessageRepository extends EloquentRepository implements ContactMessageRepositoryInterface
{
    protected string $modelClass = ContactMessage::class;

    public function search(array $filters = [], int $limit = 200): Collection
    {
        $query = $this->query()->with(['handledBy', 'user']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['topic'])) {
            $query->where('topic', $filters['topic']);
        }

        if (! empty($filters['q'])) {
            // Gõ mã phiếu, tên, email hay một cụm trong nội dung đều tìm ra cùng một phiếu.
            $keyword = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($filters['q'])).'%';

            $query->where(function (Builder $q) use ($keyword) {
                $q->where('ticket_code', 'like', $keyword)
                    ->orWhere('name', 'like', $keyword)
                    ->orWhere('email', 'like', $keyword)
                    ->orWhere('phone', 'like', $keyword)
                    ->orWhere('message', 'like', $keyword);
            });
        }

        /*
         * Phiếu MỚI luôn nổi lên đầu, rồi mới tới đang xử lý, rồi phần đã xong — quản trị viên
         * mở màn này ra là thấy ngay việc chưa ai làm, không phải tự dò trong danh sách.
         */
        return $query
            ->orderByRaw("CASE status WHEN 'new' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'resolved' THEN 2 ELSE 3 END")
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    public function countAll(): int
    {
        return $this->query()->count();
    }

    public function countsByStatus(): array
    {
        $counts = $this->query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        // Trạng thái chưa có phiếu nào vẫn phải ra số 0 chứ không được thiếu khoá.
        $result = [];

        foreach (ContactMessageStatus::cases() as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }

    public function countToday(): int
    {
        return $this->query()->whereDate('created_at', now()->toDateString())->count();
    }

    public function countNew(): int
    {
        return $this->query()->where('status', ContactMessageStatus::New->value)->count();
    }
}
