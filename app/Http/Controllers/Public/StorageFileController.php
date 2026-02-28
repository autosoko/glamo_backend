<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\PublicFileUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StorageFileController extends Controller
{
    public function show(Request $request, string $path): BinaryFileResponse
    {
        $normalized = PublicFileUrl::normalizePath($path);

        if ($normalized === '' || str_contains($normalized, '..')) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($normalized)) {
            abort(404);
        }

        $absolutePath = $disk->path($normalized);
        $lastModified = $disk->lastModified($normalized);
        $etag = sha1($normalized . '|' . $lastModified . '|' . (string) @filesize($absolutePath));

        $response = response()->file($absolutePath);
        $response->setPublic();
        $response->setEtag($etag);
        $response->setLastModified(\DateTimeImmutable::createFromFormat('U', (string) $lastModified) ?: new \DateTimeImmutable());
        $response->headers->set('Cache-Control', 'public, max-age=3600');

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }
}
