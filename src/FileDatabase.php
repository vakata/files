<?php

namespace vakata\files;

use vakata\http\RequestInterface;
use vakata\database\DBInterface;

/**
 * An extension to the FileStorage class, which persists any data to a database along with the actual file data.
 */
class FileDatabase extends FileDatabaseStorage
{
    /**
     * Create an instance
     * @param  string            $baseDirectory the base directory to create temp files in (for chunked uploads)
     * @param  DBInterface       $db            a database connection instance to use for storage
     * @param  string            $table         optional table name to store to, defaults to `"uploads"`
     */
    public function __construct($baseDirectory, DBInterface $db, $table = 'uploads')
    {
        parent::__construct($baseDirectory, $db, $table);
        $this->prefix = '';
    }
    /**
     * Store a stream.
     * @param  resource   $handle   the stream to read and store
     * @param  string     $name     the name to use for the stream
     * @param  mixed      $settings optional data to save along with the file
     * @return array              an array consisting of the ID, name, path, hash and size of the copied file
     */
    public function fromStream($handle, $name, $settings = null)
    {
        $data = parent::fromStream($handle, $name, $settings);

        if ($data['complete']) {
            try {
                $handle = fopen($data['path'], 'r');
                // update
                if ($this->db->driver() === 'oracle') {
                    $trans = $this->db->begin();
                    $this->db->query(
                        "UPDATE {$this->table} SET data = EMPTY_BLOB() WHERE id = ? RETURNING data INTO ?",
                        [ $data['id'], $handle ]
                    );
                    $this->db->commit($trans);
                } else {
                    $this->db->query(
                        "UPDATE {$this->table} SET data = ? WHERE id = ?",
                        [ $handle, $data['id'] ]
                    );
                }
                fclose($handle);
                if (strpos($data['path'], $this->baseDirectory) === 0) {
                    @unlink($data['path']);
                }
            } catch (\Exception $e) {
                throw new FileException('Could not save file');
            }
        }

        return $data;
    }
    /**
     * Get a file's metadata from storage.
     * @param  mixed $id  the file ID to return
     * @param  bool  $contents  should the result include the file path, defaults to false
     * @return array      an array consisting of the ID, name, path, hash and size of the file
     */
    public function get($id, $contents = false)
    {
        $data = $this->db->one("SELECT id, name, hash, bytesize, settings FROM {$this->table} WHERE id = ?", $id);
        if (!$data) {
            throw new FileNotFoundException('File not found', 404);
        }
        if ($contents) {
            $name = tempnam($this->baseDirectory, "DWN");
            file_put_contents($name, $this->db->one("SELECT data FROM {$this->table} WHERE id = ?", $id));
            @register_shutdown_function(function () use ($name) { @unlink($name); });
        }
        return [
            'id'       => $data['id'],
            'name'     => $data['name'],
            'path'     => $contents ? $name : null,
            'complete' => true,
            'hash'     => $data['hash'],
            'size'     => $data['bytesize'],
            'settings' => json_decode($data['settings'], true)
        ];
    }
}
