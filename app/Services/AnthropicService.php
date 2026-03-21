<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class AnthropicService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    public function __construct(
        private string $apiKey,
        private string $model,
        private int $maxTokens,
    ) {}

    /**
     * Send a message to the Anthropic API.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{role: string, content: string, usage: array{input_tokens: int, output_tokens: int}}
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function sendMessage(array $messages, ?string $system = null): array
    {
        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => $messages,
        ];

        if ($system !== null) {
            $payload['system'] = $system;
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
        ])
            ->timeout(120)
            ->throw()
            ->post(self::API_URL, $payload);

        $data = $response->json();

        return [
            'role' => $data['role'],
            'content' => $data['content'][0]['text'],
            'usage' => $data['usage'],
        ];
    }

    /**
     * Send a single user prompt with an optional system instruction.
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function ask(string $prompt, ?string $system = null): string
    {
        $result = $this->sendMessage(
            messages: [['role' => 'user', 'content' => $prompt]],
            system: $system,
        );

        return $result['content'];
    }
}
