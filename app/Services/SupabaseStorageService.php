<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupabaseStorageService
{
    public function isEnabled(): bool
    {
        return config('supabase.enabled')
            && ! empty(config('supabase.url'));
    }

    public function uploadPoster(UploadedFile $file): string
    {
        $this->ensureEnabled();

        $objectPath = $this->buildObjectPath($file);
        $endpoint = $this->buildEndpoint($objectPath);

        $response = Http::withHeaders($this->defaultHeaders([
            'Content-Type' => $file->getMimeType() ?: 'application/octet-stream',
        ]))
            ->withBody(file_get_contents($file->getRealPath()), $file->getMimeType() ?: 'application/octet-stream')
            ->post($endpoint);

        if (! $response->successful()) {
            Log::warning('Supabase upload failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Unable to upload poster to Supabase Storage.');
        }

        return $this->makePublicUrl($objectPath);
    }

    public function deletePoster(string $posterUrl): void
    {
        $this->ensureEnabled();

        $objectPath = $this->extractObjectPath($posterUrl);

        if ($objectPath === null) {
            return;
        }

        Http::withHeaders($this->defaultHeaders())
            ->delete(rtrim(config('supabase.url'), '/') . "/storage/v1/object/" . config('supabase.bucket') . "/{$objectPath}");
    }

    public function ownsUrl(string $posterUrl): bool
    {
        $needle = rtrim(config('supabase.url'), '/') . '/storage/v1/object/public/' . config('supabase.bucket') . '/';

        return Str::startsWith($posterUrl, $needle);
    }

    private function buildObjectPath(UploadedFile $file): string
    {
        $prefix = config('supabase.poster_prefix') ?: 'movies';
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $filename = sprintf('%s.%s', Str::uuid(), $extension);

        return trim($prefix . '/' . $filename, '/');
    }

    private function buildEndpoint(string $objectPath): string
    {
        return rtrim(config('supabase.url'), '/') . '/storage/v1/object/' . config('supabase.bucket') . '/' . $objectPath;
    }

    private function makePublicUrl(string $objectPath): string
    {
        return rtrim(config('supabase.url'), '/') . '/storage/v1/object/public/' . config('supabase.bucket') . '/' . $objectPath;
    }

    private function extractObjectPath(string $posterUrl): ?string
    {
        $needle = '/storage/v1/object/public/' . config('supabase.bucket') . '/';

        if (! Str::contains($posterUrl, $needle)) {
            return null;
        }

        return Str::after($posterUrl, $needle);
    }

    private function defaultHeaders(array $overrides = []): array
    {
        $key = config('supabase.service_role_key');

        if (empty($key)) {
            return $overrides;
        }

        return array_merge([
            'Authorization' => 'Bearer ' . $key,
            'apikey' => $key,
        ], $overrides);
    }

    private function ensureEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('Supabase Storage service is not configured.');
        }
    }
}
