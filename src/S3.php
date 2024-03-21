<?php

declare(strict_types=1);

namespace vakata\files;

use RuntimeException;

class S3 implements CloudInterface
{
    protected string $endpoint;
    protected string $region;
    protected string $bucket;
    protected string $access;
    protected string $secret;
    
    public static function fromFile(string $path, string $bucket): self
    {
        $auth = json_decode(file_get_contents($path), true);
        $endp = $auth['url'] ?? '';
        if (!strlen($endp)) {
            $endp = "http://s3.amazonaws.com";
        }
        return new self($auth['access'], $auth['secret'], $bucket, $endp);
    }

    public function __construct(
        string $access,
        string $secret,
        string $bucket,
        string $endpoint = "http://s3.amazonaws.com",
        string $region = "us-east-1"
    )
    {
        $this->access = $access;
        $this->secret = $secret;
        $this->bucket = $bucket;
        $this->endpoint = $endpoint;
        $this->region = $region;
    }

    protected function request(
        string $uri,
        array $parameters = [],
        string $data = '',
        ?string $method = null,
        bool $stream = false
    ): mixed
    {
        $method = $method ?? (strlen($data) ? 'POST' : 'GET');
        $http = [];
        $amaz = [];
        $amaz['x-amz-date'] = gmdate('Ymd\THis\Z');
        $amaz['x-amz-content-sha256'] = hash('sha256', $data);
        $http['Host'] = parse_url($this->endpoint, PHP_URL_HOST);
        $http['Date'] = gmdate('D, d M Y H:i:s T');

        if (strlen($data)) {
            $http['Content-Type'] = 'application/octet-stream';
            $http['Content-Length'] = (string)strlen($data);
        }

        $sort = function ($a, $b) {
            $lenA = strlen($a);
            $lenB = strlen($b);
            $minLen = min($lenA, $lenB);
            $ncmp = strncmp($a, $b, $minLen);
            if ($lenA == $lenB) return $ncmp;
            if (0 == $ncmp) return $lenA < $lenB ? -1 : 1;
            return $ncmp;
        };

        $service = 's3';
        $region = $this->region;
        $algorithm = 'AWS4-HMAC-SHA256';
        $headers = array();
        $amzDateStamp = substr($amaz['x-amz-date'], 0, 8);
        foreach ($http as $k => $v) {
            $headers[strtolower($k)] = trim($v);
        }
        foreach ($amaz as $k => $v) {
            $headers[strtolower($k)] = trim($v);
        }
        uksort($headers, $sort);

        $parameters = array_map('strval', $parameters); 
        uksort($parameters, $sort);
        $queryString = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    
        // Payload
        $amzPayload = array($method);

        $qsPos = strpos($uri, '?');
        $amzPayload[] = ($qsPos === false ? $uri : substr($uri, 0, $qsPos));
        $amzPayload[] = $queryString;
        foreach ($headers as $k => $v) {
            $amzPayload[] = $k . ':' . $v;
        }
        // add a blank entry so we end up with an extra line break
        $amzPayload[] = '';
        // SignedHeaders
        $amzPayload[] = implode(';', array_keys($headers));
        // payload hash
        $amzPayload[] = $amaz['x-amz-content-sha256'];
        // request as string
        $amzPayloadStr = implode("\n", $amzPayload);
        // CredentialScope
        $credentialScope = array($amzDateStamp, $region, $service, 'aws4_request');
        // stringToSign
        $stringToSignStr = implode(
            "\n",
            [
                $algorithm,
                $amaz['x-amz-date'],
                implode('/', $credentialScope),
                hash('sha256', $amzPayloadStr)
            ]
        );
        // Make Signature
        $kSecret = 'AWS4' . $this->secret;
        $kDate = hash_hmac('sha256', $amzDateStamp, $kSecret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSignStr, $kSigning);
        $authorization = $algorithm . ' ' . implode(',', array(
            'Credential=' . $this->access . '/' . implode('/', $credentialScope),
            'SignedHeaders=' . implode(';', array_keys($headers)),
            'Signature=' . $signature,
        ));

        $http['Authorization'] = $authorization;

        $headers = [];
        foreach ($amaz as $k => $v) {
            $headers[] = $k . ': ' . $v;
        }
        foreach ($http as $k => $v) {
            $headers[] = $k . ': ' . $v;
        }

        $res = $stream ?
            file_get_contents(
                $this->endpoint . $uri . (strlen($queryString) ? '?' . $queryString : ''),
                false,
                stream_context_create([
                    'http' => [
                        'method' => $method,
                        'header' => $headers,
                        'content' => strlen($data) ? $data : null
                    ]
                ])
            ) :
            fopen(
                $this->endpoint . $uri . (strlen($queryString) ? '?' . $queryString : ''),
                'rb',
                false,
                stream_context_create([
                    'http' => [
                        'method' => $method,
                        'header' => $headers,
                        'content' => strlen($data) ? $data : null
                    ]
                ])
            );
        if ($res === false) {
            throw new RuntimeException('Could not list bucket');
        }
        return $res;
    }

    public function list(): array
    {
        $temp = [];
        $params = [];
        do {
            $res = $this->request('/' . $this->bucket, $params);
            $res = json_decode(json_encode(simplexml_load_string($res)), true);
            $params = [];
            if (isset($res['Contents'])) {
                $contents = [];
                if (isset($res['Contents']['Key'])) {
                    $contents = [$res['Contents']];
                } else {
                    $contents = $res['Contents'];
                }
                foreach ($contents as $file) {
                    $temp[] = $file['Key'];
                }
            }
            if (isset($res['NextMarker']) && $res['NextMarker']) {
                $params['marker'] = $res['NextMarker'];
            }
        } while ($res['IsTruncated'] === 'true');
        return $temp;
    }
    public function delete(string $name): void
    {
        $this->request('/' . $this->bucket . '/' . $name, [], '', 'DELETE');
    }
    public function get(string $name): string
    {
        return $this->request('/' . $this->bucket . '/' . $name);
    }
    public function stream(string $name): mixed
    {
        return $this->request('/' . $this->bucket . '/' . $name, [], '', null, true);
    }
    public function upload(mixed $handle, ?string $name = null): string
    {
        if (is_resource($handle)) {
            if (!$name) {
                throw new RuntimeException('No name specified for handle');
            }
        }
        if (is_string($handle)) {
            if (is_file($handle)) {
                $name = $name ?? basename($handle);
                $handle = fopen($handle, 'r');
            } else if (is_dir($handle)) {
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
                return $this->endpoint . '/' . $this->bucket . '/' . $name;
            }
        }
        if (!is_resource($handle)) {
            throw new RuntimeException('Invalid handle');
        }
        $this->request('/' . $this->bucket . '/' . $name, [], stream_get_contents($handle), 'PUT');
        return $this->endpoint . '/' . $this->bucket . '/' . $name;
    }
}
