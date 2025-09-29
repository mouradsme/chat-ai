<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class OllamaService
{
    private string $baseUrl;
    private string $model;
    private string $embedModel;

    public function __construct()
    {
        $this->baseUrl = config('app.ollama_base_url', env('OLLAMA_BASE_URL', 'http://localhost:11434'));
        $this->model = config('app.ollama_model', env('OLLAMA_MODEL', 'mistral:7b'));
        $this->embedModel = config('app.ollama_embed_model', env('OLLAMA_EMBED_MODEL', 'mistral:7b-embed'));
    }

    /**
     * Generate embedding for given text.
     *
     * @param string $text
     * @return array
     * @throws Exception
     */
    public function generateEmbedding(string $text): array
    {
        try {
            $response = Http::timeout(60)->post("{$this->baseUrl}/api/embeddings", [
                'model' => $this->embedModel,
                'prompt' => $text,
            ]);

            if (!$response->successful()) {
                throw new Exception("Ollama API error: " . $response->body());
            }

            $data = $response->json();
            
            if (!isset($data['embedding'])) {
                throw new Exception("Invalid response from Ollama API: missing embedding");
            }

            return $data['embedding'];
        } catch (Exception $e) {
            Log::error("Failed to generate embedding: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate text response using the chat model.
     *
     * @param string $prompt
     * @param array $options
     * @return string
     * @throws Exception
     */
    public function generateResponse(string $prompt, array $options = []): string
    {
        try {
            $payload = [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => array_merge([
                    'temperature' => 0.7,
                    'top_p' => 0.9,
                    'max_tokens' => 1000,
                ], $options)
            ];

            $response = Http::timeout(120)->post("{$this->baseUrl}/api/generate", $payload);

            if (!$response->successful()) {
                throw new Exception("Ollama API error: " . $response->body());
            }

            $data = $response->json();
            
            if (!isset($data['response'])) {
                throw new Exception("Invalid response from Ollama API: missing response");
            }

            return trim($data['response']);
        } catch (Exception $e) {
            Log::error("Failed to generate response: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if Ollama service is available.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/tags");
            return $response->successful();
        } catch (Exception $e) {
            Log::warning("Ollama service not available: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get list of available models.
     *
     * @return array
     */
    public function getAvailableModels(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/tags");
            
            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();
            return $data['models'] ?? [];
        } catch (Exception $e) {
            Log::error("Failed to get available models: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Build RAG prompt with context and question.
     *
     * @param array $contexts
     * @param string $question
     * @return string
     */
    public function buildRagPrompt(array $contexts, string $question): string
    {
        $contextText = implode("\n\n", array_map(function ($context) {
            return "- " . trim($context);
        }, $contexts));

        return "You are a helpful chatbot assistant. Use ONLY the provided context to answer the question. If the answer is not in the context, say \"I don't know based on the provided information.\"\n\n" .
               "Context:\n{$contextText}\n\n" .
               "Question: {$question}\n\n" .
               "Answer:";
    }
}