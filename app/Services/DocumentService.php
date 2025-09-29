<?php

namespace App\Services;

use App\Models\Chatbot;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToText\Pdf;
use Exception;

class DocumentService
{
    private OllamaService $ollamaService;
    private int $chunkSize;

    public function __construct(OllamaService $ollamaService)
    {
        $this->ollamaService = $ollamaService;
        $this->chunkSize = config('app.chunk_size', env('CHUNK_SIZE', 500));
    }

    /**
     * Process uploaded file and create document chunks with embeddings.
     *
     * @param UploadedFile $file
     * @param Chatbot $chatbot
     * @return array
     * @throws Exception
     */
    public function processFile(UploadedFile $file, Chatbot $chatbot): array
    {
        $filename = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());

        // Extract text from file
        $text = $this->extractTextFromFile($file, $extension);
        
        if (empty(trim($text))) {
            throw new Exception("No text content found in the uploaded file.");
        }

        // Split text into chunks
        $chunks = $this->chunkText($text);
        
        if (empty($chunks)) {
            throw new Exception("Failed to create text chunks from the file.");
        }

        $documents = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($chunks as $index => $chunk) {
            try {
                // Generate embedding for chunk
                $embedding = $this->ollamaService->generateEmbedding($chunk);
                
                // Create document record
                $document = Document::create([
                    'chatbot_id' => $chatbot->id,
                    'filename' => $filename,
                    'chunk_text' => $chunk,
                    'chunk_index' => $index,
                    'token_count' => $this->estimateTokenCount($chunk),
                    'embedding' => $embedding,
                ]);

                $documents[] = $document;
                $successCount++;
                
                Log::info("Created document chunk {$index} for chatbot {$chatbot->id}");
            } catch (Exception $e) {
                $errorCount++;
                Log::error("Failed to process chunk {$index}: " . $e->getMessage());
            }
        }

        return [
            'success' => $successCount > 0,
            'total_chunks' => count($chunks),
            'successful_chunks' => $successCount,
            'failed_chunks' => $errorCount,
            'documents' => $documents,
            'filename' => $filename,
        ];
    }

    /**
     * Extract text from uploaded file based on file type.
     *
     * @param UploadedFile $file
     * @param string $extension
     * @return string
     * @throws Exception
     */
    private function extractTextFromFile(UploadedFile $file, string $extension): string
    {
        switch ($extension) {
            case 'pdf':
                return $this->extractTextFromPdf($file);
            case 'txt':
                return file_get_contents($file->getRealPath());
            default:
                throw new Exception("Unsupported file type: {$extension}");
        }
    }

    /**
     * Extract text from PDF file.
     *
     * @param UploadedFile $file
     * @return string
     * @throws Exception
     */
    private function extractTextFromPdf(UploadedFile $file): string
    {
        try {
            $pdfPath = $file->getRealPath();
            $text = Pdf::getText($pdfPath);
            
            if (empty(trim($text))) {
                throw new Exception("PDF appears to be empty or contains no extractable text.");
            }
            
            return $text;
        } catch (Exception $e) {
            throw new Exception("Failed to extract text from PDF: " . $e->getMessage());
        }
    }

    /**
     * Split text into chunks of approximately specified token count.
     *
     * @param string $text
     * @return array
     */
    private function chunkText(string $text): array
    {
        // Clean and normalize text
        $text = $this->cleanText($text);
        
        // Split by sentences first
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        if (empty($sentences)) {
            return [];
        }

        $chunks = [];
        $currentChunk = '';
        $currentTokenCount = 0;

        foreach ($sentences as $sentence) {
            $sentenceTokenCount = $this->estimateTokenCount($sentence);
            
            // If adding this sentence would exceed chunk size, start new chunk
            if ($currentTokenCount + $sentenceTokenCount > $this->chunkSize && !empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
                $currentChunk = $sentence;
                $currentTokenCount = $sentenceTokenCount;
            } else {
                $currentChunk .= ($currentChunk ? ' ' : '') . $sentence;
                $currentTokenCount += $sentenceTokenCount;
            }
        }

        // Add the last chunk if it's not empty
        if (!empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }

        return array_filter($chunks, function ($chunk) {
            return strlen(trim($chunk)) > 10; // Filter out very short chunks
        });
    }

    /**
     * Clean and normalize text.
     *
     * @param string $text
     * @return string
     */
    private function cleanText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove special characters but keep basic punctuation
        $text = preg_replace('/[^\w\s\.\!\?\,\;\:\-\(\)\"\']/u', '', $text);
        
        return trim($text);
    }

    /**
     * Estimate token count for text (rough approximation).
     *
     * @param string $text
     * @return int
     */
    private function estimateTokenCount(string $text): int
    {
        // Rough estimation: 1 token ≈ 4 characters for English text
        return (int) ceil(strlen($text) / 4);
    }

    /**
     * Delete all documents for a chatbot.
     *
     * @param Chatbot $chatbot
     * @return int Number of deleted documents
     */
    public function deleteAllDocuments(Chatbot $chatbot): int
    {
        return $chatbot->documents()->delete();
    }

    /**
     * Get document statistics for a chatbot.
     *
     * @param Chatbot $chatbot
     * @return array
     */
    public function getDocumentStats(Chatbot $chatbot): array
    {
        $documents = $chatbot->documents();
        
        return [
            'total_documents' => $documents->count(),
            'total_chunks' => $documents->sum('chunk_index') + $documents->count(),
            'total_tokens' => $documents->sum('token_count'),
            'unique_files' => $documents->distinct('filename')->count('filename'),
        ];
    }
}