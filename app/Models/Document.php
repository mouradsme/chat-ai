<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use App\Services\PgVectorService;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'chatbot_id',
        'filename',
        'chunk_text',
        'chunk_index',
        'token_count'
    ];

    protected $casts = [
        'chatbot_id' => 'integer',
        'chunk_index' => 'integer',
        'token_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the chatbot that owns this document.
     */
    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }

    /**
     * Set the embedding vector.
     */
    public function setEmbedding(array $embedding): void
    {
        $vectorString = PgVectorService::arrayToVector($embedding);
        DB::table('documents')
            ->where('id', $this->id)
            ->update(['embedding' => DB::raw("'{$vectorString}'::vector")]);
    }

    /**
     * Get the embedding vector as array.
     */
    public function getEmbedding(): ?array
    {
        $result = DB::table('documents')
            ->select('embedding')
            ->where('id', $this->id)
            ->first();

        if (!$result || !$result->embedding) {
            return null;
        }

        return PgVectorService::vectorToArray($result->embedding);
    }

    /**
     * Find similar documents using pgvector cosine similarity.
     */
    public static function findSimilar(
        array $queryEmbedding,
        int $limit = 10,
        float $threshold = 0.7
    ) {
        $vectorString = PgVectorService::arrayToVector($queryEmbedding);
        
        return DB::table('documents')
            ->selectRaw('documents.*, (1 - (embedding <=> ?::vector)) as similarity', [$vectorString])
            ->whereRaw('(1 - (embedding <=> ?::vector)) >= ?', [$vectorString, $threshold])
            ->orderByRaw('embedding <=> ?::vector', [$vectorString])
            ->limit($limit)
            ->get();
    }

    /**
     * Find similar documents for a specific chatbot.
     */
    public static function findSimilarForChatbot(
        int $chatbotId,
        array $queryEmbedding,
        int $limit = 10,
        float $threshold = 0.7
    ) {
        $vectorString = PgVectorService::arrayToVector($queryEmbedding);
        
        return DB::table('documents')
            ->selectRaw('documents.*, (1 - (embedding <=> ?::vector)) as similarity', [$vectorString])
            ->where('chatbot_id', $chatbotId)
            ->whereRaw('(1 - (embedding <=> ?::vector)) >= ?', [$vectorString, $threshold])
            ->orderByRaw('embedding <=> ?::vector', [$vectorString])
            ->limit($limit)
            ->get();
    }
}