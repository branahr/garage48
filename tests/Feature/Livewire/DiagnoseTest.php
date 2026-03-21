<?php

use App\Livewire\Diagnose;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config([
        'services.anthropic.api_key' => 'test-key',
        'services.anthropic.model' => 'claude-sonnet-4-20250514',
        'services.anthropic.max_tokens' => 4096,
    ]);
});

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

it('submits and displays the analysis', function () {
    $json = json_encode([
        'score' => 7,
        'summary' => 'Decent but vague.',
        'strengths' => ['Mentions specific services'],
        'weaknesses' => ['No target audience'],
        'missing_target_audience' => 'Unclear who this is for',
        'missing_value_proposition' => 'No clear outcome promised',
        'jargon' => ['UX'],
        'decision_questions_needed' => 5,
        'decision_questions_reason' => 'Need to clarify audience and scope.',
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'role' => 'assistant',
            'content' => [
                ['type' => 'text', 'text' => $json],
            ],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ]),
    ]);

    Livewire::test(Diagnose::class)
        ->set('description', 'I help businesses with UX design, branding, and strategy consulting.')
        ->call('diagnose')
        ->assertSet('submitted', true)
        ->assertSee('7/10')
        ->assertSee('Decent but vague.')
        ->assertSee('Mentions specific services')
        ->assertSee('No target audience');
});

it('stores the description in session', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'role' => 'assistant',
            'content' => [
                ['type' => 'text', 'text' => json_encode(['score' => 5, 'summary' => 'OK'])],
            ],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    Livewire::test(Diagnose::class)
        ->set('description', 'I help businesses with UX design, branding, and strategy consulting.')
        ->call('diagnose');

    expect(session('service_description'))
        ->toBe('I help businesses with UX design, branding, and strategy consulting.');
});

it('restores description from session on mount', function () {
    session(['service_description' => 'Previously entered text about my design service.']);

    Livewire::test(Diagnose::class)
        ->assertSet('description', 'Previously entered text about my design service.');
});

it('can start over and clear state', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'role' => 'assistant',
            'content' => [
                ['type' => 'text', 'text' => json_encode(['score' => 5, 'summary' => 'OK'])],
            ],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    Livewire::test(Diagnose::class)
        ->set('description', 'I help businesses with UX design, branding, and strategy consulting.')
        ->call('diagnose')
        ->assertSet('submitted', true)
        ->call('startOver')
        ->assertSet('description', '')
        ->assertSet('analysis', [])
        ->assertSet('submitted', false);
});
