<?php

declare(strict_types=1);

namespace vakata\files;

use RuntimeException;

class GCS implements CloudInterface
{
    const BASEURI = 'https://storage.googleapis.com';
    
    protected string $bucket;
    protected string $token;
    
    public static function fromFile(string $path, string $defaultBucket): self
    {
        $auth = json_decode(file_get_contents($path), true);
        return self::fromKey($auth['client_email'], $auth['private_key'], $defaultBucket);
    }
    public static function fromKey(string $email, string $key, string $defaultBucket): self
    {
        $token = new \vakata\jwt\JWT(
            [
                "iss" => $email,
                "exp" => time() + 1800,
                "iat" => time(),
                "scope" => implode(
                    ' ',
                    [
                        "https://www.googleapis.com/auth/iam",
                        "https://www.googleapis.com/auth/devstorage.full_control"
                    ]
                ),
                "sub" => $email,
            ],
            'RS256'
        );
        $token->sign($key);
        return new self($token->toString(), $defaultBucket);
    }
    public function __construct(string $token, string $defaultBucket)
    {
        $this->token = $token;
        $this->bucket = $defaultBucket;
    }
    public function list(): array
    {
        $bucket = $this->bucket;
        $json = file_get_contents(
            self::BASEURI . '/storage/v1/b/' . $bucket . '/o',
            false,
            stream_context_create([
                'http' => [
                    'header' => 'Authorization: Bearer ' . $this->token
                ]
            ])
        );
        $json = json_decode($json, true);
        $temp = [];
        foreach ($json['items'] ?? [] as $o) {
            $temp[$o['id']] = $o['name'];
        }
        return $temp;
    }
    public function delete(string $name): void
    {
        $bucket = $this->bucket;
        file_get_contents(
            self::BASEURI . '/storage/v1/b/' . $bucket . '/o/' . urlencode($name),
            false,
            stream_context_create([
                'http' => [
                    'method' => 'DELETE',
                    'header' => 'Authorization: Bearer ' . $this->token
                ]
            ])
        );
    }
    public function get(string $name): string
    {
        $bucket = $this->bucket;
        return file_get_contents(
            self::BASEURI . '/storage/v1/b/' . $bucket . '/o/' . urlencode($name) . '?alt=media',
            false,
            stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'Authorization: Bearer ' . $this->token
                ]
            ])
        );
    }
    public function stream(string $name): mixed
    {
        $bucket = $this->bucket;
        $res = fopen(
            self::BASEURI . '/storage/v1/b/' . $bucket . '/o/' . urlencode($name) . '?alt=media',
            'rb',
            false,
            stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'Authorization: Bearer ' . $this->token
                ]
            ])
        );
        if ($res === false) {
            throw new RuntimeException('Could not list bucket');
        }
        return $res;
    }
    public function upload(mixed $handle, ?string $name = null): string
    {
        $bucket = $this->bucket;
        if (is_resource($handle)) {
            if (!$name) {
                throw new RuntimeException('No name specified for handle');
            }
        }
        if (is_string($handle)) {
            if (is_file($handle)) {
                $name = $name ?? basename($handle);
                $handle = fopen($handle, 'r');
            }
            if (is_dir($handle)) {
                $path = realpath($handle);
                $name = $name ?? basename($path);
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(
                        $path,
                        \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO
                    ),
                    \RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($files as $k => $object) {
                    if ($object->isFile()) {
                        $this->upload($k, ltrim($name . str_replace('\\', '/', substr($k, strlen($path))), '/'));
                    }
                }
                return self::BASEURI . '/' . $bucket . '/' . $name;
            }
        }
        if (!is_resource($handle)) {
            throw new RuntimeException('Invalid handle');
        }
        // file_get_contents(
        //     self::BASEURI . '/upload/storage/v1/b/' . $bucket . '/o?uploadType=media&name=' . $name,
        //     false,
        //     stream_context_create([
        //         'http' => [
        //             'method' => 'POST',
        //             'header' => '' .
        //                 'Content-Type: application/octet-stream' . "\r\n" .
        //                 'Authorization: Bearer ' . $this->token . "\r\n",
        //             'content' => stream_get_contents($handle)
        //         ]
        //     ])
        // );
        $res = file_get_contents(
            self::BASEURI . '/' . $bucket . '/' . $name . '?uploads',
            false,
            stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => '' .
                        'Content-Type: application/octet-stream' . "\r\n" .
                        'Authorization: Bearer ' . $this->token . "\r\n",
                    'content' => ''
                ]
            ])
        );
        $res = json_decode(json_encode(simplexml_load_string($res)), true);
        $uid = $res['UploadId'];
        $ptn = 0;
        $res = [];
        while (!feof($handle) && ($data = fread($handle, 5 * 1024 * 1024))) {
            file_get_contents(
                self::BASEURI . '/' . $bucket . '/' . $name . '?partNumber='.(++$ptn).'&uploadId=' . $uid,
                false,
                stream_context_create([
                    'http' => [
                        'method' => 'PUT',
                        'header' => '' .
                            'Content-Type: application/octet-stream' . "\r\n" .
                            'Authorization: Bearer ' . $this->token . "\r\n",
                        'content' => $data
                    ]
                ])
            );
            $headers = [];
            foreach ($http_response_header ?? [] as $k => $v) {
                if ($k === 0) {
                    continue;
                }
                $temp = explode(':', $v, 2);
                if (count($temp) === 2) {
                    $headers[trim($temp[0])] = trim($temp[1]);
                }
            }
            $res[] =
                '<Part>
                    <PartNumber>'.$ptn.'</PartNumber>
                    <ETag>'.trim($headers['ETag'], '"').'</ETag>
                </Part>';
        }
        $res = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<CompleteMultipartUpload>' . "\n" .
            implode("\n", $res) . "\n" .
            '</CompleteMultipartUpload>';
        file_get_contents(
            self::BASEURI . '/' . $bucket . '/' . $name . '?uploadId=' . $uid,
            false,
            stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => '' .
                        'Content-Type: application/xml' . "\r\n" .
                        'Authorization: Bearer ' . $this->token . "\r\n",
                    'content' => $res
                ]
            ])
        );
        return self::BASEURI . '/' . $bucket . '/' . $name;
    }
}
