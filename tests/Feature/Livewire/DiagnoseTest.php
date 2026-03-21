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

function fakeAnthropicSequence(array $jsonResponses): void
{
    $responses = array_map(fn (string $json) => Http::response([
        'role' => 'assistant',
        'content' => [['type' => 'text', 'text' => $json]],
        'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
    ]), $jsonResponses);

    Http::fake([
        'api.anthropic.com/*' => Http::sequence($responses),
    ]);
}

function fakeQuestionsResponse(): array
{
    return [
        'who' => 'Think about your best client — what was happening right before they contacted you?',
        'what_do' => 'What is the ONE core thing you actually do for your clients?',
        'what_dont' => 'What do clients sometimes ask for that you always say no to?',
        'why' => 'What changes for your client after working with you?',
    ];
}

function fakeNoFollowupResponse(): array
{
    return ['needs_followup' => false, 'followup_question' => null];
}

function fakeFollowupResponse(): array
{
    return ['needs_followup' => true, 'followup_question' => 'Can you give a specific example?'];
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

// ── Step 2 → 3: Proceed to questions ──

it('step 2: proceedToQuestions creates tagged questions and moves to step 3', function () {
    fakeAnthropicHttp(json_encode(fakeQuestionsResponse()));

    $session = DiagnosisSession::factory()->diagnosed()->create();

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 2)
        ->call('proceedToQuestions')
        ->assertSet('step', 3)
        ->assertSet('questionStep', 'who')
        ->assertSee('best client');

    $session->refresh();
    expect($session->questions)->toHaveCount(4);
    expect($session->questions->where('step', 'who')->count())->toBe(1);
    expect($session->questions->where('step', 'what')->count())->toBe(2);
    expect($session->questions->where('step', 'why')->count())->toBe(1);
});

// ── Steps 3/4/5: Who / What / Why Q&A ──

it('step 3: requires an answer before advancing', function () {
    $session = DiagnosisSession::factory()->diagnosed()->create(['step' => 3]);
    $session->questions()->create(['step' => 'who', 'question' => 'Who do you serve?', 'sort_order' => 0]);

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 3)
        ->set('questionStep', 'who')
        ->set('currentAnswer', '')
        ->call('submitAnswer')
        ->assertHasErrors(['currentAnswer' => 'required']);
});

it('step 3: answer with no follow-up advances to What step', function () {
    fakeAnthropicHttp(json_encode(fakeNoFollowupResponse()));

    $session = DiagnosisSession::factory()->diagnosed()->create(['step' => 3]);
    $session->questions()->createMany([
        ['step' => 'who', 'question' => 'Who do you serve?', 'sort_order' => 0],
        ['step' => 'what', 'question' => 'What do you do?', 'sort_order' => 1],
        ['step' => 'what', 'question' => 'What don\'t you do?', 'sort_order' => 2],
        ['step' => 'why', 'question' => 'Why you?', 'sort_order' => 3],
    ]);

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 3)
        ->set('questionStep', 'who')
        ->set('currentAnswer', 'Freelancers who lost a client')
        ->call('submitAnswer')
        ->assertSet('step', 4)
        ->assertSet('questionStep', 'what')
        ->assertSet('currentAnswer', '');

    expect($session->questions()->where('step', 'who')->first()->answer)
        ->toBe('Freelancers who lost a client');
});

it('step 4: What step has two questions and advances after both answered', function () {
    $session = DiagnosisSession::factory()->diagnosed()->create(['step' => 4]);
    $session->questions()->createMany([
        ['step' => 'who', 'question' => 'Who?', 'answer' => 'Freelancers', 'sort_order' => 0],
        ['step' => 'what', 'question' => 'What do you do?', 'sort_order' => 1],
        ['step' => 'what', 'question' => 'What don\'t you do?', 'sort_order' => 2],
        ['step' => 'why', 'question' => 'Why you?', 'sort_order' => 3],
    ]);

    // Answer first What question — should stay on What (second unanswered)
    $component = Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 4)
        ->set('questionStep', 'what')
        ->set('currentAnswer', 'I rewrite service descriptions')
        ->call('submitAnswer')
        ->assertSet('step', 4)
        ->assertSet('questionStep', 'what');

    // Answer second What question — should advance to Why
    $component
        ->set('currentAnswer', 'I don\'t build websites or do marketing')
        ->call('submitAnswer')
        ->assertSet('step', 5)
        ->assertSet('questionStep', 'why');
});

it('step 3: vague answer triggers follow-up question on same step', function () {
    fakeAnthropicHttp(json_encode(fakeFollowupResponse()));

    $session = DiagnosisSession::factory()->diagnosed()->create(['step' => 3]);
    $session->questions()->createMany([
        ['step' => 'who', 'question' => 'Who do you serve?', 'sort_order' => 0],
        ['step' => 'what', 'question' => 'What do you do?', 'sort_order' => 1],
        ['step' => 'what', 'question' => 'What don\'t you do?', 'sort_order' => 2],
        ['step' => 'why', 'question' => 'Why you?', 'sort_order' => 3],
    ]);

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 3)
        ->set('questionStep', 'who')
        ->set('currentAnswer', 'Small businesses')
        ->call('submitAnswer')
        ->assertSet('step', 3)
        ->assertSet('questionStep', 'who')
        ->assertSee('Can you give a specific example?');

    expect($session->fresh()->questions->where('step', 'who')->count())->toBe(2);
});

it('step 3: second answer on same step always advances', function () {
    fakeAnthropicHttp(json_encode(fakeNoFollowupResponse()));

    $session = DiagnosisSession::factory()->diagnosed()->create(['step' => 3]);
    $session->questions()->createMany([
        ['step' => 'who', 'question' => 'Who do you serve?', 'answer' => 'Small businesses', 'sort_order' => 0],
        ['step' => 'who', 'question' => 'Can you be more specific?', 'sort_order' => 1],
        ['step' => 'what', 'question' => 'What do you do?', 'sort_order' => 2],
        ['step' => 'what', 'question' => 'What don\'t you do?', 'sort_order' => 3],
        ['step' => 'why', 'question' => 'Why you?', 'sort_order' => 4],
    ]);

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 3)
        ->set('questionStep', 'who')
        ->set('currentAnswer', 'Yoga studios who just opened')
        ->call('submitAnswer')
        ->assertSet('step', 4)
        ->assertSet('questionStep', 'what');
});

it('step 5: completing Why generates result and moves to step 6', function () {
    fakeAnthropicSequence([
        json_encode(fakeNoFollowupResponse()),
        json_encode(DiagnosisSessionFactory::finalResultData()),
    ]);

    $session = DiagnosisSession::factory()->diagnosed()->create(['step' => 5]);
    $session->questions()->createMany([
        ['step' => 'who', 'question' => 'Who?', 'answer' => 'Freelancers', 'sort_order' => 0],
        ['step' => 'what', 'question' => 'What do you do?', 'answer' => 'Rewrite descriptions', 'sort_order' => 1],
        ['step' => 'what', 'question' => 'What don\'t you do?', 'answer' => 'No websites', 'sort_order' => 2],
        ['step' => 'why', 'question' => 'Why you?', 'sort_order' => 3],
    ]);

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 5)
        ->set('questionStep', 'why')
        ->set('currentAnswer', 'Clients get 3x more leads after working with me')
        ->call('submitAnswer')
        ->assertSet('step', 6);

    expect($session->fresh()->status)->toBe('completed');
    expect($session->fresh()->final_result)->not->toBeNull();
});

// ── Step 6: Final result ──

it('step 6: displays before score and client-ready badge', function () {
    $session = DiagnosisSession::factory()->completed()->create();

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 6)
        ->assertSee('Before')
        ->assertSee('client-ready')
        ->assertSee($session->diagnosis['clarity_score']);
});

it('step 6: displays service description and one-liner', function () {
    $session = DiagnosisSession::factory()->completed()->create();

    Livewire::test(Diagnose::class)
        ->set('sessionId', $session->id)
        ->set('step', 6)
        ->assertSee($session->final_result['service_description'])
        ->assertSee($session->final_result['one_liner']);
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
