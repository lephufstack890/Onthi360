<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Models\CompetitionExamPaper;
use App\Services\Contest\ContestOrganizerService;
use App\Services\Contest\ContestPaperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 24/10 — API JSON cho tab "Thiết lập đề" trong phòng thi (ban tổ chức). Mọi lệnh kiểm quyền
 * ContestOrganizerService::canManage() và đề phải thuộc đúng kỳ / cuộc thi trên đường dẫn. Luật: ContestPaperService.
 */
class ContestPaperController extends Controller
{
    public function __construct(
        private readonly ContestOrganizerService $organizers,
        private readonly ContestPaperService $papers,
    ) {}

    public function state(Request $request, int $competition, int $exam): JsonResponse
    {
        return $this->run($request, $competition, $exam, null, fn (CompetitionExam $e) => null);
    }

    public function create(Request $request, int $competition, int $exam): JsonResponse
    {
        return $this->run($request, $competition, $exam, null, fn (CompetitionExam $e) => ['notice' => 'Đã thêm '.$this->papers->createPaper($e)->label.'. Thiết lập ma trận rồi sinh đề.']);
    }

    /** Đếm câu khớp từng phần + lỗi ma trận (gọi lại mỗi khi sửa ma trận). */
    public function check(Request $request, int $competition, int $exam, int $paper): JsonResponse
    {
        return $this->run($request, $competition, $exam, $paper, function (CompetitionExam $e) use ($request) {
            $matrix = $this->papers->normalizeMatrix($request->input('matrix', []));
            $exclude = $request->boolean('excludeUsed');
            $usage = $this->papers->usage($e);

            return ['counts' => $this->papers->counts($matrix, $exclude, $usage), 'issues' => $this->papers->issues($matrix, $exclude, $usage), 'skipState' => true];
        });
    }

    public function generate(Request $request, int $competition, int $exam, int $paper): JsonResponse
    {
        return $this->run($request, $competition, $exam, $paper, function (CompetitionExam $e) use ($request) {
            $usage = $this->papers->usage($e);
            $questions = $this->papers->generate($this->papers->normalizeMatrix($request->input('matrix', [])), $request->boolean('excludeUsed'), $usage);

            return ['questions' => $questions, 'preview' => $this->papers->preview($questions, $usage), 'skipState' => true,
                'notice' => 'Đã sinh đề dự kiến. Kiểm tra từng câu trước khi chốt; chưa tính lượt sử dụng.'];
        });
    }

    public function replace(Request $request, int $competition, int $exam, int $paper): JsonResponse
    {
        return $this->run($request, $competition, $exam, $paper, function (CompetitionExam $e) use ($request) {
            $usage = $this->papers->usage($e);
            $questions = $this->papers->replace(
                $this->papers->normalizeMatrix($request->input('matrix', [])),
                $request->boolean('excludeUsed'),
                $usage,
                (array) $request->input('questions', []),
                $request->integer('replaceId'),
            );

            return ['questions' => $questions, 'preview' => $this->papers->preview($questions, $usage), 'skipState' => true];
        });
    }

    public function save(Request $request, int $competition, int $exam, int $paper): JsonResponse
    {
        return $this->run($request, $competition, $exam, $paper, function (CompetitionExam $e, CompetitionExamPaper $p) use ($request) {
            $input = $request->only(['matrix', 'excludeUsed', 'questions', 'duration', 'label']);
            if ($request->boolean('publish')) {
                $this->papers->publish($p, $request->user(), $input);

                return ['notice' => 'Đã chốt '.$p->label.'. Học sinh được giao đề này (ngẫu nhiên giữa các đề đã chốt) và giữ nguyên cho mọi lượt làm lại.'];
            }
            $this->papers->saveDraft($p, $input);

            return ['notice' => 'Đã lưu bản nháp. Chỉ đề đã chốt mới được giao cho học sinh.'];
        });
    }

    public function destroy(Request $request, int $competition, int $exam, int $paper): JsonResponse
    {
        return $this->run($request, $competition, $exam, $paper, function (CompetitionExam $e, CompetitionExamPaper $p) {
            $label = $p->label;
            $this->papers->deletePaper($p);

            return ['notice' => 'Đã xoá '.$label.'.'];
        });
    }

    /** Kiểm quyền + nạp kỳ / đề, chạy thao tác, trả JSON (kèm trạng thái mới của tab). */
    private function run(Request $request, int $competitionId, int $examId, ?int $paperId, callable $action): JsonResponse
    {
        abort_unless(CompetitionExamPaper::supported(), 409, 'Chưa chạy migration 001280 — chạy "php artisan migrate".');

        $competition = Competition::query()->with('advisors:id,name')->findOrFail($competitionId);
        $exam = CompetitionExam::query()->with('assessment:id,title')->where('competition_id', $competition->id)->findOrFail($examId);
        // SỬA 25/10 — chỉ người chủ trì kỳ (hoặc admin) được thiết lập đề của kỳ.
        $this->organizers->assertCanManageRound($request->user(), $competition, $exam);
        $paper = $paperId !== null ? CompetitionExamPaper::query()->where('competition_exam_id', $exam->id)->findOrFail($paperId) : null;

        try {
            $result = $paper !== null ? $action($exam, $paper) : $action($exam);
        } catch (ValidationException $e) {
            return response()->json(['error' => collect($e->errors())->flatten()->first() ?? 'Không thực hiện được.'], 422);
        }

        $result = (array) $result;
        if (empty($result['skipState'])) {
            $result['state'] = $this->papers->builderState($exam->fresh(['assessment:id,title']));
        }
        unset($result['skipState']);

        return response()->json($result);
    }
}
