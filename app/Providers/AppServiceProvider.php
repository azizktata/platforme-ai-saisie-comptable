<?php

namespace App\Providers;

use App\Contracts\OcrProvider;
use App\Services\Ocr\MistralOcrProvider;
use App\Services\Ocr\OcrSpaceProvider;
use App\Services\Ocr\OpenRouterOcrProvider;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OcrProvider::class, function ($app): OcrProvider {
            return match (config('services.ocr.provider', 'ocr_space')) {
                'ocr_space' => $app->make(OcrSpaceProvider::class),
                'mistral' => $app->make(MistralOcrProvider::class),
                'openrouter' => $app->make(OpenRouterOcrProvider::class),
                default => throw new InvalidArgumentException('The configured OCR provider is not supported.'),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
