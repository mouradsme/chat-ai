<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chatbot;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ChatbotController extends Controller
{
    private DocumentService $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    /**
     * Display a listing of chatbots.
     */
    public function index(): JsonResponse
    {
        $chatbots = Chatbot::with(['documents' => function ($query) {
            $query->select('chatbot_id', 'filename')
                  ->distinct('filename');
        }])->get();

        return response()->json([
            'success' => true,
            'data' => $chatbots->map(function ($chatbot) {
                return [
                    'id' => $chatbot->id,
                    'name' => $chatbot->name,
                    'description' => $chatbot->description,
                    'is_active' => $chatbot->is_active,
                    'document_count' => $chatbot->documents->count(),
                    'created_at' => $chatbot->created_at,
                    'updated_at' => $chatbot->updated_at,
                ];
            })
        ]);
    }

    /**
     * Store a newly created chatbot.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'is_active' => 'boolean',
                'settings' => 'nullable|array',
            ]);

            $chatbot = Chatbot::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Chatbot created successfully',
                'data' => $chatbot
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Display the specified chatbot.
     */
    public function show(Chatbot $chatbot): JsonResponse
    {
        $stats = $this->documentService->getDocumentStats($chatbot);

        return response()->json([
            'success' => true,
            'data' => [
                'chatbot' => $chatbot,
                'stats' => $stats
            ]
        ]);
    }

    /**
     * Update the specified chatbot.
     */
    public function update(Request $request, Chatbot $chatbot): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'is_active' => 'boolean',
                'settings' => 'nullable|array',
            ]);

            $chatbot->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Chatbot updated successfully',
                'data' => $chatbot
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Remove the specified chatbot.
     */
    public function destroy(Chatbot $chatbot): JsonResponse
    {
        $chatbot->delete();

        return response()->json([
            'success' => true,
            'message' => 'Chatbot deleted successfully'
        ]);
    }
}