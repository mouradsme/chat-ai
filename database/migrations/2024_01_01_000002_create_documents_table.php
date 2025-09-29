<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable pgvector extension if not already enabled
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_id')->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->text('chunk_text');
            $table->integer('chunk_index'); // Position of chunk in original document
            $table->integer('token_count')->nullable(); // Number of tokens in chunk
            $table->timestamps();
            
            $table->index(['chatbot_id', 'chunk_index']);
            $table->index(['chatbot_id', 'created_at']);
        });
        
        // Add vector column for embeddings using raw SQL
        DB::statement('ALTER TABLE documents ADD COLUMN embedding vector(4096)');
        
        // Create index for vector similarity search
        DB::statement('CREATE INDEX documents_embedding_idx ON documents USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};