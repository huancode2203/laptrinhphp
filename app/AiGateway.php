<?php

namespace App;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiGateway
{
    public function structured(string $task, string $instructions, string $input, array $schema): array
    {
        $provider = config('ai.'.$task.'.provider');
        $model = config('ai.'.$task.'.model');
        if ($provider === 'openai') {
            if (! config('ai.openai_key')) {
                throw new RuntimeException('AI chưa được kết nối.');
            }
            $response = Http::withToken(config('ai.openai_key'))->timeout(config('ai.timeout'))->post('https://api.openai.com/v1/responses', [
                'model' => $model, 'store' => false, 'instructions' => $instructions, 'input' => $input,
                'text' => ['format' => ['type' => 'json_schema', 'name' => $task, 'strict' => true, 'schema' => $schema]], 'max_output_tokens' => 800,
            ])->throw()->json();
            $content = collect($response['output'] ?? [])->where('type', 'message')->flatMap(fn ($m) => $m['content'] ?? [])->where('type', 'output_text')->pluck('text')->implode('');
        } elseif ($provider === 'ollama') {
            $response = Http::timeout(config('ai.timeout'))->post(rtrim(config('ai.ollama_url'), '/').'/api/chat', [
                'model' => $model, 'stream' => false, 'think' => false, 'format' => $schema,
                'messages' => [['role' => 'system', 'content' => $instructions], ['role' => 'user', 'content' => $input]], 'options' => ['temperature' => 0, 'num_predict' => 700],
            ])->throw()->json();
            $content = $response['message']['content'] ?? '';
        } else {
            throw new RuntimeException('Chưa chọn model AI.');
        }
        $decoded = json_decode($content, true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('Kết quả AI không hợp lệ.');
        }

        return $decoded;
    }
}
