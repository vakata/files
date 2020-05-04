<?php

namespace vakata\files;

use Psr\Http\Message\ServerRequestInterface;

/**
 * A file storage class used to move desired files to a location on disk.
 */
class FileStorage implements FileStorageInterface
{
    protected $mode;
    protected $prefix;
    protected $baseDirectory;

    /**
     * Create an instance.
     * @param  string      $baseDirectory the base directory to store files in
     */
    public function __construct(string $baseDirectory, int $mode = 0)
    {
        $this->baseDirectory = rtrim($baseDirectory, '/') . '/';
        $this->prefix = date('Y/m/d/');
        $this->mode = $mode;
    }
    /**
     * Save additional settings for a file.
     * @param  int|string|array $file     file id or array
     * @param  mixed           $settings data to store for the file
     * @return array                     the file array (as from getFile)
     */
    public function saveSettings($file, $settings): array
    {
        if (!is_array($file)) {
            $file = $this->get($file, false);
        }
        file_put_contents($file['path'] . '.settings', json_encode($settings));
        $file['settings'] = $settings;
        return $file;
    }
    /**
     * Store a stream.
     * @param  resource   $handle   the stream to read and store
     * @param  string     $name     the name to use for the stream
     * @param  mixed      $settings optional data to save along with the file
     * @return array                an array consisting of the ID, name, path, hash and size of the copied file
     */
    public function fromStream($handle, string $name, $settings = null)
    {
        $cnt = 0;
        $uen = urlencode($name);
        if (strlen($uen) > 245) { // keep total length under 255
            $uen = preg_replace(['(%[a-f0-9]*$)i', '(%D0$)i'], '', substr($uen, 0, 245));
        }
        do {
            $newName = sprintf('%04d', $cnt++) . '.' . $uen . '_up';
        } while (file_exists($this->baseDirectory . $this->prefix . $newName));

        if (!is_dir($this->baseDirectory . $this->prefix) && !mkdir($this->baseDirectory . $this->prefix, 0755, true)) {
            throw new FileException('Could not create upload directory');
        }

        $path = fopen($this->baseDirectory . $this->prefix . $newName, 'w');
        while (!feof($handle)) {
            fwrite($path, fread($handle, 4096));
        }
        fclose($path);
        @chmod($path, 0664);

        $file = [
            'id'       => $this->prefix . $newName,
            'name'     => $name,
            'path'     => $this->baseDirectory . $this->prefix . $newName,
            'complete' => true,
            'hash'     => md5_file($this->baseDirectory . $this->prefix . $newName),
            'size'     => filesize($this->baseDirectory . $this->prefix . $newName),
            'settings' => $settings
        ];

        if (isset($settings)) {
            $this->saveSettings($file, $settings);
        }

        return $file;
    }
    /**
     * Store a string in the file storage.
     * @param  string     $content  the string to store
     * @param  string     $name     the name to store the string under
     * @param  mixed      $settings optional data to save along with the file
     * @return array                an array consisting of the ID, name, path, hash and size of the copied file
     */
    public function fromString(string $content, string $name, $settings = null)
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        return $this->fromStream($handle, $name, $settings);
    }
    /**
     * Copy an existing file to the storage
     * @param  string   $path the path to the file
     * @param  string   $name the optional name to store the string under (defaults to the current file name)
     * @param  mixed    $settings optional data to save along with the file
     * @return array              an array consisting of the ID, name, path, hash and size of the copied file
     */
    public function fromFile(string $path, ?string $name = null, $settings = null): array
    {
        if (!is_file($path)) {
            throw new FileException('Not a valid file', 400);
        }
        $name = $name === null ? basename($path) : $name;
        return $this->fromStream(fopen($path, 'r'), $name, $settings);
    }

    /**
     * Store an upload from a PSR-7 compatible Request object (supports chunked upload)
     * @param  ServerRequestInterface $request the request to process
     * @param  string           $key      optional upload key, defaults to `"file"`
     * @param  string|null      $name     an optional name to store under (defaults to the upload name or the post field)
     * @param  mixed            $settings optional data to save along with the file
     * @param  mixed            $user     optional user identifying code
     * @return array                      an array consisting of the ID, name, path, hash and size of the copied file
     */
    public function fromPSRRequest(ServerRequestInterface $request, string $key = 'file', ?string $name = null, $settings = null, $user = null): array
    {
        $files = $request->getUploadedFiles();
        if (!isset($files[$key])) {
            throw new FileException('No valid input files', 400);
        }
        $upload = $files[$key];
        $user   = $user ?: session_id();
        $name   = $name ?? ($upload->getClientFilename() !== 'blob' ? $upload->getClientFilename() : ($request->getParsedBody()["name"] ?? "blob"));
        $size   = (int)($request->getParsedBody()["size"] ?? 0);
        $chunk  = (int)($request->getParsedBody()['chunk'] ?? 0);
        $chunks = (int)($request->getParsedBody()['chunks'] ?? 0);
        $done   = $chunks <= 1 || $chunk >= $chunks - 1;
        $temp   = $this->prefix . md5(implode('.', [
            $name,
            $chunks,
            $size,
            $user,
            isset($_SERVER) && isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
        ]));
        if ($chunk === 0 && is_file($this->baseDirectory . $temp)) {
            throw new FileException('The same file is already being uploaded', 400);
        }
        if ($chunk > 0 && !is_file($this->baseDirectory . $temp)) {
            throw new FileException('No previous file parts', 400);
        }

        if (!is_dir($this->baseDirectory . $this->prefix) && !mkdir($this->baseDirectory . $this->prefix, 0775, true)) {
            throw new FileException('Could not create upload directory');
        }
        if ($chunk === 0) {
            $upload->moveTo($this->baseDirectory . $temp);
        } else {
            $upload->moveTo($this->baseDirectory . $temp . '_');
            $inp = fopen($this->baseDirectory . $temp . '_', 'r');
            $out = fopen($this->baseDirectory . $temp, 'a');
            stream_copy_to_stream($inp, $out);
            fclose($out);
            fclose($inp);
            unlink($this->baseDirectory . $temp . '_');
        }

        if ($done) {
            $data = $this->fromFile($this->baseDirectory . $temp, $name, $settings);
            unlink($this->baseDirectory . $temp);
            return $data;
        }

        return [
            'id'       => $temp,
            'name'     => $name,
            'path'     => $this->baseDirectory . $temp,
            'complete' => false,
            'hash'     => '',
            'size'     => 0
        ];
    }

    /**
     * Get a file's metadata from storage.
     * @param  mixed $id        the file ID to return
     * @param  bool  $contents  should the result include the file path, defaults to false
     * @return array      an array consisting of the ID, name, path, hash and size of the file
     */
    public function get($id, bool $contents = false): array
    {
        if (!is_file($this->baseDirectory . $id)) {
            throw new FileNotFoundException('File not found', 404);
        }
        return [
            'id'       => $id,
            'name'     => substr(basename($id), 5, -3),
            'path'     => $contents ? $this->baseDirectory . $id : null,
            'complete' => true,
            'hash'     => md5_file($this->baseDirectory . $id),
            'size'     => filesize($this->baseDirectory . $id),
            'settings' => is_file($this->baseDirectory . $id) ?
                json_decode($this->baseDirectory . $id . '.settings', true) :
                null
        ];
    }
}
