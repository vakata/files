<?php

namespace vakata\files;

use vakata\http\RequestInterface;
use vakata\http\UploadInterface;

interface FileStorageInterface
{
    public function saveSettings($file, $settings);
    public function fromStream($handle, $name, $settings = null);
    public function fromString($content, $name, $settings = null);
    public function fromFile($path, $name = null, $settings = null);
    public function fromUpload(UploadInterface $upload, $name = null, $settings = null);
    public function fromRequest(RequestInterface $request, $key = 'file', $name = null, $settings = null);
    public function get($id, $contents = false);
}
