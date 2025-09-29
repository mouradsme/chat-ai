<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Document extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'chatbot_id',
        'filename',
        'chunk_text',
        'chunk_index',
        'token_count',
        'embedding',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'chatbot_id' => 'integer',
        'chunk_index' => 'integer',
        'token_count' => 'integer',
    ];

    /**
     * Get the chatbot that owns the document.
     */
    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }

    /**
     * Set the embedding attribute.
     * Converts array to PostgreSQL vector format.
     */
    public function setEmbeddingAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['embedding'] = '[' . implode(',', $value) . ']';
        } else {
            $this->attributes['embedding'] = $value;
        }
    }

    /**
     * Get the embedding attribute.
     * Converts PostgreSQL vector format to array.
     */
    public function getEmbeddingAttribute($value)
    {
        if (is_string($value) && str_starts_with($value, '[') && str_ends_with($value, ']')) {
            return json_decode($value);
        }
        return $value;
    }

    /**
     * Find similar documents using cosine similarity.
     *
     * @param array $queryEmbedding
     * @param int $limit
     * @param float $threshold
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function scopeSimilarTo($query, array $queryEmbedding, int $limit = 3, float $threshold = 0.7)
    {
        $embeddingString = '[' . implode(',', $queryEmbedding) . ']';
        
        return $query->select('*')
            ->selectRaw('1 - (embedding <=> ?) as similarity', [$embeddingString])
            ->whereRaw('1 - (embedding <=> ?) > ?', [$embeddingString, $threshold])
            ->orderByRaw('embedding <=> ?', [$embeddingString])
            ->limit($limit);
    }

    /**
     * Find similar documents for a specific chatbot.
     *
     * @param int $chatbotId
     * @param array $queryEmbedding
     * @param int $limit
     * @param float $threshold
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function findSimilarForChatbot(int $chatbotId, array $queryEmbedding, int $limit = 3, float $threshold = 0.7)
    {
        $embeddingString = '[' . implode(',', $queryEmbedding) . ']';
        
        return self::where('chatbot_id', $chatbotId)
            ->select('*')
            ->selectRaw('1 - (embedding <=> ?) as similarity', [$embeddingString])
            ->whereRaw('1 - (embedding <=> ?) > ?', [$embeddingString, $threshold])
            ->orderByRaw('embedding <=> ?', [$embeddingString])
            ->limit($limit)
            ->get();
    }
}