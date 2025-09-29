<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chatbot;
use App\Services\DocumentService;
use App\Services\OllamaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Exception;

class DocumentController extends Controller
{
    private DocumentService $documentService;
    private OllamaService $ollamaService;

    public function __construct(DocumentService $documentService, OllamaService $ollamaService)
    {
        $this->documentService = $documentService;
        $this->ollamaService = $ollamaService;
    }

    /**
     * Upload and process a file for a chatbot.
     */
    public function upload(Request $request, Chatbot $chatbot): JsonResponse
    {
        try {
            // Check if Ollama service is available
            if (!$this->ollamaService->isAvailable()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ollama service is not available. Please ensure Ollama is running.'
                ], 503);
            }

            $validated = $request->validate([
                'file' => [
                    'required',
                    'file',
                    'max:' . (config('app.max_file_size', env('MAX_FILE_SIZE', 10240))), // KB
                    'mimes:pdf,txt'
                ]
            ]);

            $file = $validated['file'];
            
            // Process the file
            $result = $this->documentService->processFile($file, $chatbot);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process the uploaded file.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'File uploaded and processed successfully',
                'data' => [
                    'filename' => $result['filename'],
                    'total_chunks' => $result['total_chunks'],
                    'successful_chunks' => $result['successful_chunks'],
                    'failed_chunks' => $result['failed_chunks'],
                    'chatbot_id' => $chatbot->id
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get documents for a chatbot.
     */
    public function index(Chatbot $chatbot): JsonResponse
    {
        $documents = $chatbot->documents()
            ->select('id', 'filename', 'chunk_index', 'token_count', 'created_at')
            ->orderBy('filename')
            ->orderBy('chunk_index')
            ->get()
            ->groupBy('filename');

        $stats = $this->documentService->getDocumentStats($chatbot);

        return response()->json([
            'success' => true,
            'data' => [
                'documents' => $documents,
                'stats' => $stats
            ]
        ]);
    }

    /**
     * Delete all documents for a chatbot.
     */
    public function destroyAll(Chatbot $chatbot): JsonResponse
    {
        try {
            $deletedCount = $this->documentService->deleteAllDocuments($chatbot);

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$deletedCount} document chunks",
                'deleted_count' => $deletedCount
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting documents: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get document statistics for a chatbot.
     */
    public function stats(Chatbot $chatbot): JsonResponse
    {
        $stats = $this->documentService->getDocumentStats($chatbot);

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}