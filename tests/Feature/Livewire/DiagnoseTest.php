<?php

use App\Livewire\Diagnose;
use App\Models\DiagnosisSession;
use Database\Factories\DiagnosisSessionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.anthropic.api_key' => 'test-key',
        'services.anthropic.model' => 'claude-sonnet-4-20250514',
        'services.anthropic.max_tokens' => 4096,
    ]);
});

function fakeAnthropicHttp(string $json): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'role' => 'assistant',
            'content' => [['type' => 'text', 'text' => $json]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ]),
    ]);
}

function fakeDescribeQuestionsResponse(): array
{
    return [
        'step' => 'describe',
        'questions' => [
            [
                'id' => 'd1',
                'type' => 'single',
                'question' => 'What was happening before they came to you?',
                'options' => [
                    ['id' => 'a', 'label' => 'They lost a client due to unclear pitch'],
                    ['id' => 'b', 'label' => 'They were launching a new service'],
                    ['id' => 'c', 'label' => 'They got negative feedback on clarity'],
                    ['id' => 'other', 'label' => 'My situation is different'],
                ],
            ],
        ],
    ];
}

function fakeDecideQuestionsResponse(): array
{
    return [
        'step' => 'decide',
        'questions' => [
            [
                'id' => 'c1',
                'type' => 'single',
                'question' => 'What is the ONE core problem you solve?',
                'options' => [
                    ['id' => 'a', 'label' => 'Unclear messaging'],
                    ['id' => 'b', 'label' => 'No defined audience'],
                    ['id' => 'other', 'label' => 'Something else'],
                ],
            ],
            [
                'id' => 'c3',
                'type' => 'multi',
                'question' => 'What does your service NOT do?',
                'options' => [
                    ['id' => 'a', 'label' => "I don't build websites"],
                    ['id' => 'b', 'label' => "I don't do ongoing marketing"],
                    ['id' => 'c', 'label' => "I don't write code"],
                ],
            ],
        ],
    ];
}

function fakeValueQuestionsResponse(): array
{
    return [
        'step' => 'value',
        'questions' => [
            [
                'id' => 'v1',
                'type' => 'single',
                'question' => "What's different in your client's life after?",
                'options' => [
                    ['id' => 'a', 'label' => 'They can explain their service in one sentence'],
                    ['id' => 'b', 'label' => 'They stop losing clients to confusion'],
                    ['id' => 'other', 'label' => 'Something else'],
                ],
            ],
        ],
    ];
}

// ── Page rendering ──

it('renders the diagnose page', function () {
    $this->get('/diagnose')->assertSuccessful();
});

// ── Step 1 validation ──

it('requires a service description', function () {
    Livewire::test(Diagnose::class)
        ->set('description', '')
        ->call('diagnose')
        ->assertHasErrors(['description' => 'required']);
});

it('requires at least 20 characters', function () {
    Livewire::test(Diagnose::class)
        ->set('description', 'too short')
        ->call('diagnose')
        ->assertHasErrors(['description' => 'min']);
});

// ── Step 1 → 2: Diagnose ──

it('step 1: submits description and moves to step 2 with 5 dimensions', function () {
    fakeAnthropicHttp(json_encode(DiagnosisSessionFactory::diagnosisData()));

    Livewire::test(Diagnose::class)
        ->set('description', 'I help businesses with UX design, branding, and strategy consulting.')
        ->call('diagnose')
        ->assertSet('step', 2)
        ->assertSee('4/10')
        ->assertSee('Audience')
        ->assertSee('Problem')
        ->assertSee('Offer')
        ->assertSee('Value')
        ->assertSee('Language');

    $this->assertDatabaseHas('diagnosis_sessions', [
        'service_description' => 'I help businesses with UX design, branding, and strategy consulting.',
        'step' => 2,
    ]);
});

it('step 2: displays coach message and collapsible original text', function () {
    $session = DiagnosisSession::factory()->diagnosed()->create();

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 2)
        ->assertSee($session->diagnosis['coach_message'])
        ->assertSee('View my original description');
});

// ── Step 2 → 3: Proceed with routing ──

it('step 2: proceedFromDiagnosis generates describe questions when routing is deep', function () {
    fakeAnthropicHttp(json_encode(fakeDescribeQuestionsResponse()));

    $session = DiagnosisSession::factory()->withRouting(['describe' => 'deep'])->create();

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 2)
        ->call('proceedFromDiagnosis')
        ->assertSet('step', 3)
        ->assertSet('currentQuestionIndex', 0)
        ->assertSee('What was happening before they came to you?');

    expect($session->fresh()->questions->where('step', 'describe'))->toHaveCount(1);
});

it('step 2: skips describe and goes to decide when describe routing is skip', function () {
    // First call returns decide questions
    fakeAnthropicHttp(json_encode(fakeDecideQuestionsResponse()));

    $session = DiagnosisSession::factory()->withRouting([
        'describe' => 'skip',
        'decide' => 'deep',
    ])->create();

    // Override dimension scores to match skip routing for describe
    $diagnosis = $session->diagnosis;
    $diagnosis['dimension_scores']['audience']['score'] = 2;
    $session->update(['diagnosis' => $diagnosis]);

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 2)
        ->call('proceedFromDiagnosis')
        ->assertSet('step', 4);

    expect($session->fresh()->questions->where('step', 'decide'))->toHaveCount(2);
});

// ── Step 3-5: Question answering ──

it('step 3: submits single-select answer and advances', function () {
    $session = DiagnosisSession::factory()->diagnosed()->create(['step' => 3]);

    $session->questions()->createMany([
        [
            'step' => 'describe',
            'question_key' => 'd1',
            'type' => 'single',
            'question' => 'What situation triggers the need?',
            'options' => [
                ['id' => 'a', 'label' => 'Lost a client'],
                ['id' => 'b', 'label' => 'Launching new service'],
            ],
            'sort_order' => 0,
        ],
        [
            'step' => 'describe',
            'question_key' => 'd2',
            'type' => 'single',
            'question' => 'Who is the person?',
            'options' => [['id' => 'a', 'label' => 'A freelancer']],
            'sort_order' => 1,
        ],
    ]);

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 3)
        ->set('currentQuestionIndex', 0)
        ->set('selectedOption', 'a')
        ->call('submitQuestionAnswer')
        ->assertSet('currentQuestionIndex', 1)
        ->assertSet('selectedOption', '');

    expect($session->questions()->where('question_key', 'd1')->first()->answer)
        ->toBe(['selected' => ['a'], 'other_text' => null]);
});

it('step 3: stores other text when Other is selected', function () {
    $session = DiagnosisSession::factory()->diagnosed()->create(['step' => 3]);

    $session->questions()->create([
        'step' => 'describe',
        'question_key' => 'd1',
        'type' => 'single',
        'question' => 'What situation?',
        'options' => [
            ['id' => 'a', 'label' => 'Option A'],
            ['id' => 'other', 'label' => 'Other'],
        ],
        'sort_order' => 0,
    ]);

    // This will trigger advanceToNextQuestionStep since it's the last question.
    // We need to fake HTTP for the next step generation too.
    fakeAnthropicHttp(json_encode(fakeDecideQuestionsResponse()));

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 3)
        ->set('currentQuestionIndex', 0)
        ->set('selectedOption', 'other')
        ->set('otherText', 'My custom situation')
        ->call('submitQuestionAnswer');

    expect($session->questions()->where('question_key', 'd1')->first()->answer)
        ->toBe(['selected' => ['other'], 'other_text' => 'My custom situation']);
});

it('step 4: submits multi-select answer', function () {
    $session = DiagnosisSession::factory()->diagnosed()->create(['step' => 4]);

    // Override scores so decide is deep but value is skip (so it goes to generate after decide)
    $diagnosis = $session->diagnosis;
    $diagnosis['dimension_scores']['value']['score'] = 2;
    $session->update(['diagnosis' => $diagnosis]);

    $session->questions()->create([
        'step' => 'decide',
        'question_key' => 'c3',
        'type' => 'multi',
        'question' => 'What does your service NOT do?',
        'options' => [
            ['id' => 'a', 'label' => "I don't build websites"],
            ['id' => 'b', 'label' => "I don't do marketing"],
        ],
        'sort_order' => 0,
    ]);

    // After answering last question, it will try to advance (generate result)
    fakeAnthropicHttp(json_encode(DiagnosisSessionFactory::finalResultData()));

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 4)
        ->set('currentQuestionIndex', 0)
        ->set('selectedOptions', ['a', 'b'])
        ->call('submitQuestionAnswer')
        ->assertSet('step', 6);

    expect($session->questions()->where('question_key', 'c3')->first()->answer)
        ->selected->toBe(['a', 'b']);
});

// ── Step 6: Final result ──

it('step 6: displays before/after score comparison', function () {
    $session = DiagnosisSession::factory()->completed()->create();

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 6)
        ->assertSee('Before')
        ->assertSee('After')
        ->assertSee($session->diagnosis['clarity_score'])
        ->assertSee($session->final_result['new_clarity_score']);
});

it('step 6: displays service description and one-liner', function () {
    $session = DiagnosisSession::factory()->completed()->create();

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 6)
        ->assertSee($session->final_result['service_description'])
        ->assertSee($session->final_result['one_liner']);
});

it('step 6: displays boundaries', function () {
    $session = DiagnosisSession::factory()->completed()->create();

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 6)
        ->assertSee("I don't build websites")
        ->assertSee("I don't do ongoing marketing");
});

// ── Session handling ──

it('restores session from session storage on mount', function () {
    $session = DiagnosisSession::factory()->diagnosed()->create();

    session(['diagnosis_session_id' => $session->id]);

    Livewire::test(Diagnose::class)
        ->assertSet('sessionId', $session->id)
        ->assertSet('step', 2);
});

it('can start over and clear state', function () {
    $session = DiagnosisSession::factory()->diagnosed()->create(['step' => 3]);

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 3)
        ->call('startOver')
        ->assertSet('description', '')
        ->assertSet('sessionId', null)
        ->assertSet('step', 1);
});

// ── Routing logic ──

it('skips all question steps and goes straight to generate when all routing is skip', function () {
    $session = DiagnosisSession::factory()->create([
        'diagnosis' => array_merge(DiagnosisSessionFactory::diagnosisData(), [
            'dimension_scores' => [
                'audience' => ['score' => 2, 'reason' => 'Good'],
                'problem' => ['score' => 2, 'reason' => 'Good'],
                'offer' => ['score' => 2, 'reason' => 'Good'],
                'value' => ['score' => 2, 'reason' => 'Good'],
                'language' => ['score' => 2, 'reason' => 'Good'],
            ],
        ]),
        'step' => 2,
    ]);

    fakeAnthropicHttp(json_encode(DiagnosisSessionFactory::finalResultData()));

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 2)
        ->call('proceedFromDiagnosis')
        ->assertSet('step', 6);

    expect($session->fresh()->status)->toBe('completed');
});
