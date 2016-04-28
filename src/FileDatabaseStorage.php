<?php

namespace vakata\files;

use vakata\http\RequestInterface;
use vakata\database\DatabaseInterface;

/**
 * An extension to the FileStorage class, which persists any data to a database.
 */
class FileDatabaseStorage extends FileStorage
{
    protected $db;
    protected $table;

    /**
     * Create an instance
     * @method __construct
     * @param  string            $baseDirectory the base directory to store files in
     * @param  DatabaseInterface $db            a database connection instance to use for storage
     * @param  string            $table         optional table name to store to, defaults to `"uploads"`
     */
    public function __construct($baseDirectory, DatabaseInterface $db, $table = 'uploads')
    {
        parent::__construct($baseDirectory);
        $this->db = $db;
        $this->table = $table;
    }
    /**
     * Store a stream.
     * @method fromStream
     * @param  stream     $handle the stream to read and store
     * @param  string     $name   the name to use for the stream
     * @return array              an array consisting of the ID, name, path, hash and size of the copied file
     */
    public function fromStream($handle, $name = null)
    {
        $data = parent::fromStream($handle, $name);
        if ($data['complete']) {
            $data['id'] = $this->db->query(
                "INSERT INTO {$this->table} (name, location, bytesize, uploaded, hash) VALUES (?, ?, ?, ?, ?)",
                [
                    $data['name'],
                    $data['id'],
                    $data['size'],
                    date('Y-m-d H:i:s'),
                    $data['hash']
                ]
            )->insertId();
        }
        return $data;
    }
    /**
     * Get a file's metadata from storage.
     * @method get
     * @param  mixed $id  the file ID to return
     * @return array      an array consisting of the ID, name, path, hash and size of the file
     */
    public function get($id)
    {
        $data = $this->db->one("SELECT * FROM {$this->table} WHERE id = ?", $id);
        if (!$data) {
            throw new Exception('File not found', 404);
        }
        return [
            'id'       => $data['id'],
            'name'     => $data['name'],
            'path'     => $data['location'],
            'complete' => true,
            'hash'     => $data['hash'],
            'size'     => $data['bytesize']
        ];
    }
}
