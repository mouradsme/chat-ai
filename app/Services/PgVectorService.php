<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Exception;

class PgVectorService
{
    /**
     * Convert array to pgvector format string.
     */
    public static function arrayToVector(array $embedding): string
    {
        return '[' . implode(',', $embedding) . ']';
    }

    /**
     * Convert pgvector string to array.
     */
    public static function vectorToArray(string $vector): array
    {
        // Remove brackets and split by comma
        $vector = trim($vector, '[]');
        return array_map('floatval', explode(',', $vector));
    }

    /**
     * Calculate cosine similarity between two vectors.
     */
    public static function cosineSimilarity(array $vector1, array $vector2): float
    {
        if (count($vector1) !== count($vector2)) {
            throw new Exception('Vectors must have the same dimensions');
        }

        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;

        for ($i = 0; $i < count($vector1); $i++) {
            $dotProduct += $vector1[$i] * $vector2[$i];
            $magnitude1 += $vector1[$i] * $vector1[$i];
            $magnitude2 += $vector2[$i] * $vector2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    /**
     * Find similar vectors using PostgreSQL pgvector extension.
     */
    public static function findSimilar(
        string $table,
        string $vectorColumn,
        array $queryVector,
        int $limit = 10,
        float $threshold = 0.7,
        array $additionalConditions = []
    ): array {
        $vectorString = self::arrayToVector($queryVector);
        
        $query = DB::table($table)
            ->selectRaw("*, (1 - ({$vectorColumn} <=> ?::vector)) as similarity", [$vectorString])
            ->whereRaw("(1 - ({$vectorColumn} <=> ?::vector)) >= ?", [$vectorString, $threshold]);

        // Add additional conditions
        foreach ($additionalConditions as $column => $value) {
            $query->where($column, $value);
        }

        return $query
            ->orderByRaw("({$vectorColumn} <=> ?::vector)", [$vectorString])
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Check if pgvector extension is available.
     */
    public static function isAvailable(): bool
    {
        try {
            $result = DB::select("SELECT * FROM pg_extension WHERE extname = 'vector'");
            return !empty($result);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Enable pgvector extension (requires superuser privileges).
     */
    public static function enableExtension(): bool
    {
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}