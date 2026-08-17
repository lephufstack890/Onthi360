<?php

namespace App\Support;

/**
 * Tách văn bản đề thi thô (từ Word/PDF/OCR) thành từng câu hỏi nháp (6.4).
 *
 * Đây là suy đoán heuristic dựa trên định dạng thường gặp ("Câu 1:", 4 phương
 * án A/B/C/D, dòng "Đáp án: B") — KHÔNG đảm bảo đúng 100%. Nguyên tắc bắt buộc:
 * không bao giờ âm thầm bỏ nội dung — nếu không nhận diện được ranh giới câu
 * hoặc không đủ 4 phương án, vẫn tạo 1 draft chứa nguyên văn để giáo viên tự
 * tách/sửa tay ở màn rà soát, kèm mức "confidence" thấp để tự động gắn cờ.
 */
class ExamTextSplitter
{
    /** @return array<int, array{raw_text:string,type_guess:?string,confidence:string,structured:array}> */
    public function split(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);

        $boundaries = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*(Câu|Bài|Question)\s*\d+\s*[.:\)]/iu', $line)) {
                $boundaries[] = $i;
            }
        }

        if ($boundaries === []) {
            $whole = trim($text);

            return $whole === '' ? [] : [$this->buildChunk($whole)];
        }

        $chunks = [];
        foreach ($boundaries as $idx => $start) {
            $end = $boundaries[$idx + 1] ?? count($lines);
            $chunkText = trim(implode("\n", array_slice($lines, $start, $end - $start)));

            if ($chunkText !== '') {
                $chunks[] = $this->buildChunk($chunkText);
            }
        }

        return $chunks;
    }

    /** @return array{raw_text:string,type_guess:?string,confidence:string,structured:array} */
    private function buildChunk(string $chunkText): array
    {
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $chunkText)),
            fn ($l) => $l !== ''
        ));
        $title = mb_substr($lines[0] ?? $chunkText, 0, 200);

        $options = [];
        $optionLineIdx = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*([A-D])[.\)]\s*(.+)$/u', $line, $m)) {
                $letter = strtoupper($m[1]);
                if (! isset($options[$letter])) {
                    $options[$letter] = trim($m[2]);
                    $optionLineIdx[$i] = true;
                }
            }
        }

        $correct = null;
        foreach ($lines as $line) {
            if (preg_match('/(?:Đáp\s*án|Answer)\s*[:\-]?\s*([A-D])\b/iu', $line, $m)) {
                $correct = strtoupper($m[1]);
                break;
            }
        }

        $hasAllFourOptions = isset($options['A'], $options['B'], $options['C'], $options['D']);

        if ($hasAllFourOptions) {
            $bodyLines = [];
            foreach ($lines as $i => $line) {
                if (! isset($optionLineIdx[$i])) {
                    $bodyLines[] = $line;
                }
            }
            $body = trim(implode("\n", $bodyLines));

            return [
                'raw_text' => $chunkText,
                'type_guess' => 'mcq',
                'confidence' => $correct !== null ? 'high' : 'medium',
                'structured' => [
                    'title' => $title,
                    'body' => $body !== '' ? $body : $title,
                    'points' => 1,
                    'options' => [$options['A'], $options['B'], $options['C'], $options['D']],
                    'correct_option' => $correct,
                ],
            ];
        }

        return [
            'raw_text' => $chunkText,
            'type_guess' => null,
            'confidence' => 'low',
            'structured' => [
                'title' => $title,
                'body' => $chunkText,
                'points' => 1,
            ],
        ];
    }
}
