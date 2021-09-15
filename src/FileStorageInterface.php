<?php

namespace vakata\files;

use Psr\Http\Message\ServerRequestInterface;

interface FileStorageInterface
{
    public function fromStream($handle, string $name): File;
    public function fromString(string $content, string $name): File;
    public function fromFile(string $path, ?string $name = null): File;
    public function fromPSRRequest(ServerRequestInterface $request, string $key = 'file', ?string $state = null): File;
    
    public function get(string $id): File;
    public function set(File $file, $contents = null): File;
}
