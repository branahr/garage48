<?php

use App\Services\AnthropicService;
use Illuminate\Support\Facades\Http;

it('sends a message and returns parsed response', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'role' => 'assistant',
            'content' => [
                ['type' => 'text', 'text' => 'This is the analysis result.'],
            ],
            'usage' => [
                'input_tokens' => 50,
                'output_tokens' => 20,
            ],
        ]),
    ]);

    $service = new AnthropicService(
        apiKey: 'test-key',
        model: 'claude-sonnet-4-20250514',
        maxTokens: 4096,
    );

    $result = $service->sendMessage(
        messages: [['role' => 'user', 'content' => 'Analyze this text.']],
        system: 'You are a helpful assistant.',
    );

    expect($result)
        ->role->toBe('assistant')
        ->content->toBe('This is the analysis result.')
        ->usage->toMatchArray([
            'input_tokens' => 50,
            'output_tokens' => 20,
        ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->header('x-api-key')[0] === 'test-key'
            && $request->header('anthropic-version')[0] === '2023-06-01'
            && $request['model'] === 'claude-sonnet-4-20250514'
            && $request['system'] === 'You are a helpful assistant.'
            && $request['messages'][0]['content'] === 'Analyze this text.';
    });
});

it('sends a simple ask and returns content string', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'role' => 'assistant',
            'content' => [
                ['type' => 'text', 'text' => 'Simple answer.'],
            ],
            'usage' => [
                'input_tokens' => 10,
                'output_tokens' => 5,
            ],
        ]),
    ]);

    $service = new AnthropicService(
        apiKey: 'test-key',
        model: 'claude-sonnet-4-20250514',
        maxTokens: 4096,
    );

    $result = $service->ask('What is this?');

    expect($result)->toBe('Simple answer.');
});

it('omits system parameter when null', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'role' => 'assistant',
            'content' => [
                ['type' => 'text', 'text' => 'Response.'],
            ],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 3],
        ]),
    ]);

    $service = new AnthropicService(
        apiKey: 'test-key',
        model: 'claude-sonnet-4-20250514',
        maxTokens: 4096,
    );

    $service->ask('Hello');

    Http::assertSent(function ($request) {
        return ! array_key_exists('system', $request->data());
    });
});

it('resolves from the container as a singleton', function () {
    config([
        'services.anthropic.api_key' => 'test-key',
        'services.anthropic.model' => 'claude-sonnet-4-20250514',
        'services.anthropic.max_tokens' => 4096,
    ]);

    $a = app(AnthropicService::class);
    $b = app(AnthropicService::class);

    expect($a)->toBeInstanceOf(AnthropicService::class)
        ->and($a)->toBe($b);
});
