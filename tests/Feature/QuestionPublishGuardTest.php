<?php

namespace Tests\Feature;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Services\QuestionPublishGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Test 6.2 "Điều kiện phát hành" — chặn publish khi thiếu cấu hình chấm. */
class QuestionPublishGuardTest extends TestCase
{
    use RefreshDatabase;

    private QuestionPublishGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new QuestionPublishGuard;
    }

    public function test_coding_question_without_test_cases_cannot_publish(): void
    {
        $question = Question::factory()->coding()->create(['grading_config' => null]);

        $decision = $this->guard->canPublish($question);

        $this->assertFalse($decision->allowed);
        $this->assertSame('missing_grading_config', $decision->primaryReasonCode);
    }

    public function test_coding_question_with_full_config_can_publish(): void
    {
        $question = Question::factory()->coding()->create([
            'grading_config' => [
                'test_cases' => [['input' => '1', 'output' => '1']],
                'time_limit_ms' => 1000,
                'memory_limit_mb' => 256,
            ],
        ]);

        $decision = $this->guard->canPublish($question);

        $this->assertTrue($decision->allowed);
    }

    public function test_mcq_without_answer_cannot_publish(): void
    {
        $question = Question::factory()->create(['type' => QuestionType::Mcq, 'grading_config' => [], 'points' => 0]);

        $decision = $this->guard->canPublish($question);

        $this->assertFalse($decision->allowed);
        $this->assertSame('missing_grading_config', $decision->primaryReasonCode);
    }
}
