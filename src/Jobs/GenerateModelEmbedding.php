<?php

namespace ValerianMemsk\SemanticSearch\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ValerianMemsk\SemanticSearch\Facades\SemanticSearch;

class GenerateModelEmbedding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Model $model;

    /**
     * Create a new job instance.
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            SemanticSearch::embedModel($this->model);
        } catch (\Throwable $e) {
            Log::error('[GenerateModelEmbedding] Failed to embed model '.get_class($this->model)." ID {$this->model->getKey()}: ".$e->getMessage());
        }
    }
}
