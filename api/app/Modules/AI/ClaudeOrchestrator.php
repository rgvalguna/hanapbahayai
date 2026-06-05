<?php

namespace App\Modules\AI;

use App\Models\Consultation;
use App\Models\ConsultationMessage;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class ClaudeOrchestrator
{
    private const API_URL        = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION    = '2023-06-01';
    private const MAX_TOKENS     = 4096;
    private const MAX_TOOL_ITERS = 5;

    private Client $http;
    private ToolRegistry $registry;

    public function __construct()
    {
        $this->http     = new Client(['timeout' => 120]);
        $this->registry = new ToolRegistry();
    }

    /**
     * Stream a conversation turn to the client via SSE.
     *
     * $onDelta receives typed event arrays:
     *   ['type' => 'delta',     'delta'  => string]
     *   ['type' => 'tool_call', 'tool'   => string]
     *   ['type' => 'done']
     */
    public function stream(
        Consultation $consultation,
        User $user,
        array $listings,
        callable $onDelta
    ): void {
        $model    = $consultation->model ?? 'claude-opus-4-7';
        $system   = (new PromptBuilder())->build($user);
        $messages = $this->buildMessages($consultation, $listings);
        $tools    = $this->registry->tools();

        $fullText    = '';
        $inputTokens = $outputTokens = $cacheRead = $cacheWrite = 0;

        for ($iter = 0; $iter < self::MAX_TOOL_ITERS; $iter++) {
            $iterText = '';

            [$blocks, $usage, $stopReason] = $this->callApi(
                $system,
                $messages,
                $tools,
                $model,
                function (string $text) use ($onDelta, &$fullText, &$iterText) {
                    $fullText .= $text;
                    $iterText .= $text;
                    $onDelta(['type' => 'delta', 'delta' => $text]);
                }
            );

            $inputTokens  += $usage['input_tokens']                ?? 0;
            $outputTokens += $usage['output_tokens']               ?? 0;
            $cacheRead    += $usage['cache_read_input_tokens']     ?? 0;
            $cacheWrite   += $usage['cache_creation_input_tokens'] ?? 0;

            if ($stopReason !== 'tool_use') {
                // Final turn — persist with full content_blocks for faithful replay
                ConsultationMessage::create([
                    'consultation_id'    => $consultation->id,
                    'role'               => 'assistant',
                    'content'            => $iterText,
                    'content_blocks'     => $blocks,
                    'input_tokens'       => $inputTokens,
                    'output_tokens'      => $outputTokens,
                    'cache_read_tokens'  => $cacheRead,
                    'cache_write_tokens' => $cacheWrite,
                ]);
                break;
            }

            // Tool-use turn — collect results
            $assistantContent = $blocks;
            $toolResults      = [];

            foreach ($blocks as $block) {
                if (($block['type'] ?? '') !== 'tool_use') {
                    continue;
                }

                $toolName  = $block['name'];
                $toolInput = $block['input'] ?? [];
                $toolUseId = $block['id'];

                $onDelta(['type' => 'tool_call', 'tool' => $toolName]);

                try {
                    $result = $this->registry->dispatch($toolName, $toolInput, $user);
                } catch (\Throwable $e) {
                    $result = ['error' => $e->getMessage()];
                }

                $toolResults[] = [
                    'type'        => 'tool_result',
                    'tool_use_id' => $toolUseId,
                    'content'     => json_encode($result),
                ];
            }

            // Persist intermediate assistant turn (tool_use blocks) — fixes B4
            ConsultationMessage::create([
                'consultation_id' => $consultation->id,
                'role'            => 'assistant',
                'content'         => $iterText,
                'content_blocks'  => $assistantContent,
            ]);

            // Persist tool result turn (user role with tool_result blocks) — fixes B4
            ConsultationMessage::create([
                'consultation_id' => $consultation->id,
                'role'            => 'user',
                'content'         => '',
                'content_blocks'  => $toolResults,
            ]);

            // Append for next API iteration (in-memory only)
            $messages[] = ['role' => 'assistant', 'content' => $assistantContent];
            $messages[] = ['role' => 'user',      'content' => $toolResults];
        }

        // Update consultation token totals
        $consultation->increment('total_input_tokens',        $inputTokens);
        $consultation->increment('total_output_tokens',       $outputTokens);
        $consultation->increment('total_cache_read_tokens',   $cacheRead);
        $consultation->increment('total_cache_write_tokens',  $cacheWrite);

        $onDelta(['type' => 'done']);
    }

    /**
     * Build the messages array from DB history plus an optional listing context block.
     *
     * For turns that have content_blocks stored (assistant tool-use turns, tool_result
     * turns), the full blocks array is used as the message content — this is the B4
     * fix that enables faithful multi-turn tool-context replay.
     */
    private function buildMessages(Consultation $consultation, array $listings): array
    {
        $messages = $consultation
            ->messages()
            ->orderBy('created_at')
            ->get(['role', 'content', 'content_blocks'])
            ->map(function ($m) {
                // Use full content_blocks when stored (tool-use / tool-result turns)
                if (!empty($m->content_blocks)) {
                    return ['role' => $m->role, 'content' => $m->content_blocks];
                }
                return ['role' => $m->role, 'content' => $m->content];
            })
            ->toArray();

        // Append listing context only when explicitly provided with this request.
        // Stored as in-memory context only — previous turns already captured in DB.
        if (!empty($listings)) {
            $context = "Relevant listings retrieved for this turn:\n\n"
                . json_encode($listings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $messages[] = [
                'role'    => 'user',
                'content' => $context,
            ];
        }

        return $messages;
    }

    /**
     * Make one streaming API call to Anthropic.
     *
     * Returns [$contentBlocks, $usage, $stopReason].
     * $contentBlocks is the full content array for multi-block assistant turns (text + tool_use).
     * $onText is called for every text delta during streaming.
     */
    private function callApi(
        array $system,
        array $messages,
        array $tools,
        string $model,
        callable $onText
    ): array {
        $apiKey = config('services.anthropic.key') ?: env('ANTHROPIC_API_KEY');

        $body = [
            'model'      => $model,
            'max_tokens' => self::MAX_TOKENS,
            'stream'     => true,
            'system'     => $system,
            'messages'   => $messages,
            'tools'      => $tools,
        ];

        $response = $this->http->post(self::API_URL, [
            RequestOptions::HEADERS => [
                'x-api-key'         => $apiKey,
                'anthropic-version' => self::API_VERSION,
                'anthropic-beta'    => 'prompt-caching-2024-07-31',
                'content-type'      => 'application/json',
                'accept'            => 'text/event-stream',
            ],
            RequestOptions::JSON    => $body,
            RequestOptions::STREAM  => true,
        ]);

        $stream     = $response->getBody();
        $blocks     = [];      // final content blocks (index → block data)
        $jsonAcc    = [];      // index → accumulated input_json string for tool_use
        $usage      = [];
        $stopReason = 'end_turn';

        $buffer = '';
        while (!$stream->eof()) {
            $buffer .= $stream->read(1024);

            // Process all complete SSE lines in the buffer
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line   = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $line   = rtrim($line, "\r");

                if (!str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = substr($line, 6);
                if ($data === '[DONE]') {
                    break 2;
                }

                $event = json_decode($data, true);
                if (!$event) {
                    continue;
                }

                $this->handleSseEvent($event, $blocks, $jsonAcc, $usage, $stopReason, $onText);
            }
        }

        // Decode any accumulated input_json strings
        foreach ($jsonAcc as $idx => $jsonStr) {
            if (isset($blocks[$idx])) {
                $blocks[$idx]['input'] = json_decode($jsonStr, true) ?? [];
            }
        }

        return [array_values($blocks), $usage, $stopReason];
    }

    private function handleSseEvent(
        array $event,
        array &$blocks,
        array &$jsonAcc,
        array &$usage,
        string &$stopReason,
        callable $onText
    ): void {
        $type = $event['type'] ?? '';

        switch ($type) {
            case 'message_start':
                $usage = $event['message']['usage'] ?? [];
                break;

            case 'content_block_start':
                $idx   = $event['index'];
                $block = $event['content_block'] ?? [];
                // Initialise the block slot; input will be filled by deltas
                if (($block['type'] ?? '') === 'tool_use') {
                    $blocks[$idx]   = ['type' => 'tool_use', 'id' => $block['id'], 'name' => $block['name'], 'input' => []];
                    $jsonAcc[$idx]  = '';
                } else {
                    $blocks[$idx] = $block;
                }
                break;

            case 'content_block_delta':
                $idx   = $event['index'];
                $delta = $event['delta'] ?? [];

                if ($delta['type'] === 'text_delta') {
                    $text = $delta['text'] ?? '';
                    if (!isset($blocks[$idx])) {
                        $blocks[$idx] = ['type' => 'text', 'text' => ''];
                    }
                    $blocks[$idx]['text'] = ($blocks[$idx]['text'] ?? '') . $text;
                    $onText($text);
                } elseif ($delta['type'] === 'input_json_delta') {
                    $jsonAcc[$idx] = ($jsonAcc[$idx] ?? '') . ($delta['partial_json'] ?? '');
                }
                break;

            case 'message_delta':
                $stopReason = $event['delta']['stop_reason'] ?? $stopReason;
                // Merge output_tokens update if present
                if (isset($event['usage'])) {
                    $usage = array_merge($usage, $event['usage']);
                }
                break;
        }
    }
}
