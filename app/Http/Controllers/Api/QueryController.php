<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chatbot;
use App\Models\Document;
use App\Services\OllamaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Exception;

class QueryController extends Controller
{
    private OllamaService $ollamaService;

    public function __construct(OllamaService $ollamaService)
    {
        $this->ollamaService = $ollamaService;
    }

    /**
     * Query a chatbot with RAG (Retrieval-Augmented Generation).
     */
    public function query(Request $request, Chatbot $chatbot): JsonResponse
    {
        try {
            // Check if chatbot is active
            if (!$chatbot->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'This chatbot is currently inactive.'
                ], 403);
            }

            // Check if Ollama service is available
            if (!$this->ollamaService->isAvailable()) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI service is currently unavailable. Please try again later.'
                ], 503);
            }

            $validated = $request->validate([
                'message' => 'required|string|max:1000',
                'limit' => 'integer|min:1|max:10',
                'threshold' => 'numeric|min:0|max:1'
            ]);

            $message = $validated['message'];
            $limit = $validated['limit'] ?? 3;
            $threshold = $validated['threshold'] ?? 0.7;

            // Check if chatbot has any documents
            if ($chatbot->documents()->count() === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'This chatbot has no knowledge base. Please upload documents first.'
                ], 400);
            }

            // Generate embedding for the query
            $queryEmbedding = $this->ollamaService->generateEmbedding($message);

            // Find similar documents using pgvector
            $similarDocuments = Document::findSimilarForChatbot(
                $chatbot->id,
                $queryEmbedding,
                $limit,
                $threshold
            );

            if ($similarDocuments->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => "I don't know based on the provided information.",
                    'data' => [
                        'response' => "I don't know based on the provided information.",
                        'context_used' => [],
                        'similarity_scores' => []
                    ]
                ]);
            }

            // Extract context from similar documents
            $contexts = $similarDocuments->pluck('chunk_text')->toArray();
            $similarities = $similarDocuments->pluck('similarity')->toArray();

            // Build RAG prompt
            $prompt = $this->ollamaService->buildRagPrompt($contexts, $message);

            // Generate response using Ollama
            $response = $this->ollamaService->generateResponse($prompt, [
                'temperature' => 0.7,
                'max_tokens' => 500
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Query processed successfully',
                'data' => [
                    'response' => $response,
                    'context_used' => $contexts,
                    'similarity_scores' => $similarities,
                    'query' => $message,
                    'chatbot_id' => $chatbot->id
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing query: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get chatbot status and basic information.
     */
    public function status(Chatbot $chatbot): JsonResponse
    {
        $documentCount = $chatbot->documents()->count();
        $isReady = $chatbot->is_active && $documentCount > 0;

        return response()->json([
            'success' => true,
            'data' => [
                'chatbot' => [
                    'id' => $chatbot->id,
                    'name' => $chatbot->name,
                    'description' => $chatbot->description,
                    'is_active' => $chatbot->is_active,
                    'is_ready' => $isReady
                ],
                'knowledge_base' => [
                    'document_count' => $documentCount,
                    'chunk_count' => $chatbot->documents()->count(),
                    'last_updated' => $chatbot->documents()->latest()->first()?->created_at
                ],
                'service_status' => [
                    'ollama_available' => $this->ollamaService->isAvailable()
                ]
            ]
        ]);
    }
}