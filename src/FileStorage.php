<?php

namespace vakata\files;

use Psr\Http\Message\ServerRequestInterface;

/**
 * A file storage class used to move desired files to a location on disk.
 */
class FileStorage implements FileStorageInterface
{
    protected $prefix;
    protected $baseDirectory;
    protected $tempDirectory;

    public function __construct(
        string $baseDirectory,
        ?string $prefix = null,
        ?string $tempDirectory = null
    )
    {
        $this->baseDirectory = rtrim($baseDirectory, '/') . '/';
        $this->tempDirectory = rtrim($tempDirectory ?? $baseDirectory, '/') . '/';
        $this->prefix = trim($prefix ?? date('Y/m/d/'), '/\\') . '/';
    }

    protected function getLocation(string $name): string
    {
        $cnt = 0;
        // $uen = urlencode($name);
        // if (strlen($uen) > 230) { // keep total length under 255
        //     $uen = preg_replace(['(%[a-f0-9]*$)i', '(%D0$)i'], '', substr($uen, 0, 230));
        // }
        $uen = sha1($name);
        do {
            $newName = sprintf('%04d', $cnt++) . '.' . $uen; // . '_up';
        } while (file_exists($this->baseDirectory . $this->prefix . $newName));

        return $this->prefix . $newName;
    }

    public function fromStream($handle, string $name): File
    {
        $location = $this->getLocation($name);

        if (!is_dir($this->baseDirectory . $this->prefix) && !mkdir($this->baseDirectory . $this->prefix, 0775, true)) {
            throw new FileException('Could not create upload directory');
        }

        $path = fopen($this->baseDirectory . $location, 'w');
        while (!feof($handle)) {
            fwrite($path, fread($handle, 4096));
        }
        fclose($path);
        @chmod($this->baseDirectory . $location, 0664);
        $uploaded = time();
        file_put_contents(
            $this->baseDirectory . $location . '.settings',
            json_encode([
                'name' => $name,
                'uploaded' => $uploaded,
                'data' => []
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        return new File(
            $location,
            $name,
            md5_file($this->baseDirectory . $location),
            $uploaded,
            filesize($this->baseDirectory . $location),
            [],
            true,
            $this->baseDirectory . $location
        );
    }

    public function fromString(string $content, string $name): File
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        return $this->fromStream($handle, $name);
    }

    public function fromFile(string $path, ?string $name = null): File
    {
        if (!is_file($path)) {
            throw new FileException('Not a valid file', 400);
        }
        return $this->fromStream(fopen($path, 'r'), $name ?? basename($path));
    }

    public function fromPSRRequest(ServerRequestInterface $request, string $key = 'file', ?string $state = null): File
    {
        $files = $request->getUploadedFiles();
        if (!isset($files[$key])) {
            throw new FileException('No valid input files', 400);
        }
        $upload = $files[$key];
        $state  = $state ?: session_id();
        $name   = ($upload->getClientFilename() !== 'blob' ? $upload->getClientFilename() : ($request->getParsedBody()["name"] ?? "blob"));
        $size   = (int)($request->getParsedBody()["size"] ?? 0);
        $chunk  = (int)($request->getParsedBody()['chunk'] ?? 0);
        $chunks = (int)($request->getParsedBody()['chunks'] ?? 0);
        $done   = $chunks <= 1 || $chunk >= $chunks - 1;
        $temp   = $this->prefix . md5(implode('.', [
            $name,
            $chunks,
            $size,
            $state,
            isset($_SERVER) && isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
        ]));
        if ($chunk === 0 && is_file($this->tempDirectory . $temp)) {
            throw new FileException('The same file is already being uploaded', 400);
        }
        if ($chunk > 0 && !is_file($this->tempDirectory . $temp)) {
            throw new FileException('No previous file parts', 400);
        }

        if (!is_dir($this->tempDirectory . $this->prefix) && !mkdir($this->tempDirectory . $this->prefix, 0775, true)) {
            throw new FileException('Could not create upload directory');
        }
        if ($chunk === 0) {
            $upload->moveTo($this->tempDirectory . $temp);
        } else {
            $upload->moveTo($this->tempDirectory . $temp . '_');
            $inp = fopen($this->tempDirectory . $temp . '_', 'r');
            $out = fopen($this->tempDirectory . $temp, 'a');
            stream_copy_to_stream($inp, $out);
            fclose($out);
            fclose($inp);
            unlink($this->tempDirectory . $temp . '_');
        }

        if (!$done) {
            return new File(
                $temp,
                $name,
                '',
                time(),
                0,
                [],
                false,
                $this->tempDirectory . $temp,
                ''
            );
        }

        $file = $this->fromFile($this->tempDirectory . $temp, $name);
        unlink($this->tempDirectory . $temp);
        return $file;
    }

    public function get(string $id): File
    {
        if (!is_file($this->baseDirectory . $id)) {
            throw new FileNotFoundException('File not found', 404);
        }

        if (is_file($this->baseDirectory . $id . '.settings')) {
            $settings = file_get_contents($this->baseDirectory . $id . '.settings');
            if ($settings) {
                $settings = json_decode($settings, true);
            }
            if (!is_array($settings)) {
                $settings = [];
            }
        }

        return new File(
            $id,
            $settings['name'] ?? substr(basename($id), 5, -3),
            md5_file($this->baseDirectory . $id),
            $settings['uploaded'] ?? filectime($this->baseDirectory . $id),
            filesize($this->baseDirectory . $id),
            $settings['data'] ?? [],
            true,
            $this->baseDirectory . $id
        );
    }

    public function set(File $file, $contents = null): File
    {
        $temp = $this->get($file->id());
        if ($contents !== null) {
            $handle = fopen($temp->path(), 'w');
            while (!feof($contents)) {
                fwrite($handle, fread($contents, 4096));
            }
            fclose($handle);
        }
        file_put_contents(
            $this->baseDirectory . $temp->id() . '.settings',
            json_encode([
                'name' => $file->name(),
                'uploaded' => $file->uploaded(),
                'data' => $file->settings()
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
        return $file;
    }

    public function getVersion(string $id, string $version): File
    {
        return $this->get($id . '.' . $version)->setParent($id);
    }
    public function setVersion(string $id, string $version, string $contents): File
    {
        $temp = $this->get($id);
        file_put_contents($temp->path() . '.' . sha1($version), $contents);
        return $this->getVersion($id, $version);
    }
}
