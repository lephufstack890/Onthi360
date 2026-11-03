<?php

namespace App\Support;

use App\Models\Attempt;
use App\Models\Question;

/**
 * SỬA 3/11 (khách: "Bài phổ thông — nộp cả bài mới chấm, hiện đáp án và lời giải sau khi nộp") — dựng khối "Đáp án & lời
 * giải" ở trang kết quả (partials/general-set-review) cho lượt làm "Bài phổ thông". Chỉ ĐỌC dữ liệu đã chấm; không đụng việc
 * chấm điểm. Đề khác (practice_kind null) không gọi tới lớp này.
 */
class GeneralSetReview
{
    /** @return list<array{no:int, title:string, type:string, correct:string, mine:string, ok:?bool, explanation:?string}> */
    public static function rows(Attempt $attempt): array
    {
        $assessment = $attempt->assessment;
        if ($assessment === null || ! $assessment->isGeneralSet()) {
            return [];
        }
        $assessment->loadMissing('items.question');
        $answers = $attempt->answers()->get()->keyBy('question_id');
        $typeLabels = ['mcq' => 'Trắc nghiệm', 'fill_blank' => 'Điền từ', 'composite' => 'Câu nhiều ý'];

        $rows = [];
        foreach ($assessment->items->sortBy('order')->values() as $i => $item) {
            $q = $item->question;
            if ($q === null) {
                continue;
            }
            $type = $q->type instanceof \BackedEnum ? $q->type->value : (string) $q->type;
            $answer = $answers->get($q->id);
            $raw = is_array($answer?->answer) ? $answer->answer : [];
            $verdict = $answer?->verdict instanceof \BackedEnum ? $answer->verdict->value : $answer?->verdict;

            [$correct, $mine] = self::texts($q, $type, $raw);
            $rows[] = [
                'no' => $i + 1,
                'title' => (string) $q->title,
                'type' => $typeLabels[$type] ?? 'Câu hỏi',
                'correct' => $correct,
                'mine' => $answer === null ? 'Chưa trả lời' : $mine,
                'ok' => $verdict === 'accepted' ? true : ($answer === null || in_array($verdict, ['wrong_answer'], true) ? false : null),
                'explanation' => is_string($q->metadata['explanation'] ?? null) ? $q->metadata['explanation'] : null,
            ];
        }

        return $rows;
    }

    /** @return array{0: string, 1: string} [đáp án đúng, câu trả lời của học sinh] */
    private static function texts(Question $q, string $type, array $raw): array
    {
        $cfg = is_array($q->grading_config) ? $q->grading_config : [];

        if ($type === 'mcq') {
            $options = array_values((array) ($cfg['options'] ?? []));
            $isTf = $options === ['Đúng', 'Sai'];
            $label = function ($idx) use ($options, $isTf) {
                if ($idx === null || $idx === '' || ! isset($options[(int) $idx])) {
                    return '—';
                }

                return $isTf ? (string) $options[(int) $idx] : chr(65 + (int) $idx).'. '.$options[(int) $idx];
            };
            $correct = implode(' / ', array_map($label, (array) ($cfg['correct_options'] ?? [])));

            return [$correct ?: '—', $label($raw['selected_option'] ?? null)];
        }

        if ($type === 'fill_blank') {
            $acc = array_values(array_filter(array_map('strval', (array) ($cfg['accepted_answers'] ?? []))));

            return [$acc !== [] ? implode(' hoặc ', $acc) : '—', trim((string) ($raw['text'] ?? '')) ?: '—'];
        }

        if ($type === 'composite') {
            $mineParts = is_array($raw['parts'] ?? null) ? $raw['parts'] : [];
            $c = [];
            $m = [];
            foreach ((array) ($cfg['parts'] ?? []) as $k => $part) {
                $code = (string) ($part['code'] ?? chr(97 + (int) $k));
                $right = $part['correct_answer'] ?? ($part['accepted_answers'] ?? null);
                $c[] = $code.') '.self::val($right);
                $m[] = $code.') '.self::val($mineParts[$code] ?? null);
            }

            return [implode('  ', $c) ?: '—', implode('  ', $m) ?: '—'];
        }

        return ['—', '—'];
    }

    private static function val(mixed $v): string
    {
        return match (true) {
            $v === null || $v === '' => '—',
            is_bool($v) => $v ? 'Đúng' : 'Sai',
            $v === 'true' => 'Đúng',
            $v === 'false' => 'Sai',
            is_array($v) => implode(' hoặc ', array_map('strval', $v)),
            default => (string) $v,
        };
    }
}
