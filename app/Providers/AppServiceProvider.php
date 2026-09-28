<?php

namespace App\Providers;

use App\Contracts\OcrProvider;
use App\Services\Ocr\MistralOcrProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OcrProvider::class, MistralOcrProvider::class);
    }

    public function boot(): void
    {
        //
    }
}
