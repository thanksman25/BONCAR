<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SupabaseStorageService
{
    protected $url;
    protected $key;
    protected $bucket;

    public function __construct()
    {
        $this->url = config('services.supabase.url');
        $this->key = config('services.supabase.key');
        $this->bucket = config('services.supabase.bucket');
    }

    public function upload($path, $file)
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => 'Bearer ' . $this->key,
        ])->attach(
            'file', file_get_contents($file), basename($file)
        )->post("{$this->url}/storage/v1/object/{$this->bucket}/{$path}");

        return $response->successful();
    }

    public function getPublicUrl($path)
    {
        return "{$this->url}/storage/v1/object/public/{$this->bucket}/{$path}";
    }
}
