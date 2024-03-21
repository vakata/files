<?php

namespace vakata\files;

interface CloudInterface
{
    public function upload(mixed $handle, ?string $name = null): string;
    public function delete(string $name): void;
    public function get(string $name): string;
    public function stream(string $name): mixed;
}