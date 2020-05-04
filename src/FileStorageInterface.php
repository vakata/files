<?php

namespace vakata\files;

use Psr\Http\Message\ServerRequestInterface;

interface FileStorageInterface
{
    public function saveSettings($file, $settings);
    public function fromStream($handle, string $name, $settings = null);
    public function fromString(string $content, string $name, $settings = null);
    public function fromFile(string $path, ?string $name = null, $settings = null);
    public function fromPSRRequest(ServerRequestInterface $request, string $key = 'file', ?string $name = null, $settings = null, $user = null);
    public function get($id, bool $contents = false);
}
