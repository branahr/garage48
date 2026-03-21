<?php

use App\Livewire\Diagnose;
use App\Models\DiagnosisSession;
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

function fakeDiagnosisResponse(): array
{
    return [
        'score' => 7,
        'summary' => 'Decent but vague.',
        'strengths' => ['Mentions specific services'],
        'weaknesses' => ['No target audience'],
        'missing_target_audience' => 'Unclear who this is for',
        'missing_value_proposition' => 'No clear outcome promised',
        'jargon' => ['UX'],
        'decision_questions_needed' => 3,
        'decision_questions_reason' => 'Need to clarify audience and scope.',
    ];
}

function fakeQuestionsResponse(): array
{
    return [
        'questions' => [
            'Who is your ideal client?',
            'What specific outcome do you deliver?',
            'How do you differ from competitors?',
        ],
    ];
}

function fakeResultResponse(): array
{
    return [
        'rewritten_description' => 'I help startups clarify their brand.',
        'target_audience' => 'Early-stage SaaS startups',
        'value_proposition' => 'Clear brand identity in 2 weeks',
        'positioning_statement' => 'The brand clarity expert for startups.',
        'next_steps' => ['Update your website', 'Test with 5 prospects'],
    ];
}

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

it('renders the diagnose page', function () {
    $this->get('/diagnose')->assertSuccessful();
});

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

it('step 1: submits description and moves to step 2 with diagnosis', function () {
    fakeAnthropicHttp(json_encode(fakeDiagnosisResponse()));

    Livewire::test(Diagnose::class)
        ->set('description', 'I help businesses with UX design, branding, and strategy consulting.')
        ->call('diagnose')
        ->assertSet('step', 2)
        ->assertSee('7/10')
        ->assertSee('Decent but vague.')
        ->assertSee('Mentions specific services')
        ->assertSee('No target audience');

    $this->assertDatabaseHas('diagnosis_sessions', [
        'service_description' => 'I help businesses with UX design, branding, and strategy consulting.',
        'step' => 2,
    ]);
});

it('step 2: generates questions and moves to step 3', function () {
    fakeAnthropicHttp(json_encode(fakeQuestionsResponse()));

    $session = DiagnosisSession::factory()->create([
        'diagnosis' => fakeDiagnosisResponse(),
        'step' => 2,
    ]);

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 2)
        ->call('generateQuestions')
        ->assertSet('step', 3)
        ->assertSet('currentQuestionIndex', 0)
        ->assertSee('Who is your ideal client?');

    expect($session->fresh()->questions)->toHaveCount(3);
});

it('step 3: submits answers and advances to next question', function () {
    $session = DiagnosisSession::factory()->create([
        'diagnosis' => fakeDiagnosisResponse(),
        'step' => 3,
    ]);

    $session->questions()->createMany([
        ['question' => 'Question 1?', 'sort_order' => 0],
        ['question' => 'Question 2?', 'sort_order' => 1],
    ]);

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 3)
        ->set('currentQuestionIndex', 0)
        ->set('currentAnswer', 'My answer to question 1')
        ->call('submitAnswer')
        ->assertSet('currentQuestionIndex', 1)
        ->assertSet('currentAnswer', '');

    expect($session->questions->first()->fresh()->answer)->toBe('My answer to question 1');
});

it('step 3: moves to step 4 when all questions answered', function () {
    $session = DiagnosisSession::factory()->create([
        'diagnosis' => fakeDiagnosisResponse(),
        'step' => 3,
    ]);

    $session->questions()->create(['question' => 'Only question?', 'sort_order' => 0]);

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 3)
        ->set('currentQuestionIndex', 0)
        ->set('currentAnswer', 'My only answer')
        ->call('submitAnswer')
        ->assertSet('step', 4);
});

it('step 4: generates final result and moves to step 5', function () {
    fakeAnthropicHttp(json_encode(fakeResultResponse()));

    $session = DiagnosisSession::factory()->create([
        'diagnosis' => fakeDiagnosisResponse(),
        'step' => 4,
    ]);

    $session->questions()->create(['question' => 'Q?', 'answer' => 'A.', 'sort_order' => 0]);

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 4)
        ->call('generateResult')
        ->assertSet('step', 5)
        ->assertSee('I help startups clarify their brand.')
        ->assertSee('Early-stage SaaS startups');

    expect($session->fresh())
        ->status->toBe('completed')
        ->step->toBe(5);
});

it('restores session from session storage on mount', function () {
    $session = DiagnosisSession::factory()->create([
        'step' => 2,
        'diagnosis' => fakeDiagnosisResponse(),
    ]);

    session(['diagnosis_session_id' => $session->id]);

    Livewire::test(Diagnose::class)
        ->assertSet('sessionId', $session->id)
        ->assertSet('step', 2);
});

it('can start over and clear state', function () {
    $session = DiagnosisSession::factory()->create(['step' => 3]);

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 3)
        ->call('startOver')
        ->assertSet('description', '')
        ->assertSet('sessionId', null)
        ->assertSet('step', 1);
});
