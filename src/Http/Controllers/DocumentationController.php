<?php

declare(strict_types=1);

namespace Rfpdl\WhatsUpDoc\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Rfpdl\WhatsUpDoc\Services\DataClassScanner;
use Rfpdl\WhatsUpDoc\Services\DocumentationGenerator;

class DocumentationController
{
    public function __construct(
        private readonly DataClassScanner $scanner,
        private readonly DocumentationGenerator $generator,
    ) {}

    public function index(): Response
    {
        $specUrl = route('whats-up-doc.spec');

        return response(
            view('whats-up-doc::interactive', [
                'title' => config('whats-up-doc.title', 'API Documentation'),
                'specUrl' => $specUrl,
            ])->render()
        );
    }

    public function spec(): JsonResponse
    {
        if (app()->isProduction()) {
            $openApi = Cache::remember('whats-up-doc:openapi-spec', 3600, fn () => $this->buildSpec());
        } else {
            $openApi = $this->buildSpec();
        }

        return response()->json($openApi);
    }

    private function buildSpec(): array
    {
        $dataClasses = $this->scanner->scanClasses();

        return $this->generator->buildOpenApiArray($dataClasses);
    }
}
