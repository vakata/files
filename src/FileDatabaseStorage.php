<?php

namespace vakata\files;

use vakata\http\RequestInterface;
use vakata\database\DBInterface;

/**
 * An extension to the FileStorage class, which persists any data to a database.
 */
class FileDatabaseStorage extends FileStorage
{
    protected $db;
    protected $table;

    /**
     * Create an instance
     * @param  string            $baseDirectory the base directory to store files in
     * @param  DBInterface       $db            a database connection instance to use for storage
     * @param  string            $table         optional table name to store to, defaults to `"uploads"`
     */
    public function __construct($baseDirectory, DBInterface $db, $table = 'uploads')
    {
        parent::__construct($baseDirectory);
        $this->db = $db;
        $this->table = $table;
    }
    /**
     * Save additional settings for a file.
     * @param  int|string|array $file     file id or array
     * @param  mixed           $settings data to store for the file
     * @return array                     the file array (as from getFile)
     */
    public function saveSettings($file, $settings)
    {
        if (!is_array($file)) {
            $file = $this->get($file, false);
        }
        $this->db->query(
            "UPDATE {$this->table} SET settings = ? WHERE id = ?",
            [ json_encode($settings), $file['id'] ]
        );
        $file['settings'] = $settings;
        return $file;
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
        $data = parent::fromStream($handle, $name);
        if ($data['complete']) {
            $id = 0;
            $sql = "INSERT INTO {$this->table} (name, location, bytesize, uploaded, hash, settings) VALUES (?, ?, ?, ?, ?, ?)";
            $par = [
                $data['name'],
                $data['id'],
                $data['size'],
                date('Y-m-d H:i:s'),
                $data['hash'],
                json_encode($settings)
            ];
            if ($this->db->driverName() === 'oracle') {
                $sql .= " RETURNING id INTO ?";
                $par[] = &$id;
            }
            $q = $this->db->query($sql, $par);
            $data['id'] = $this->db->driverName() === 'oracle' ? $id : $q->insertId();
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
        $data = $this->db->one(
            "SELECT id, name, location, hash, bytesize, settings FROM {$this->table} WHERE id = ?",
            $id
        );
        if (!$data) {
            throw new FileNotFoundException('File not found', 404);
        }
        if (!is_file($this->baseDirectory . $data['location'])) {
            throw new FileNotFoundException('File not found', 404);
        }
        return [
            'id'       => $data['id'],
            'name'     => $data['name'],
            'path'     => $contents ? $this->baseDirectory . $data['location'] : null,
            'complete' => true,
            'hash'     => $data['hash'],
            'size'     => $data['bytesize'],
            'settings' => json_decode($data['settings'], true)
        ];
    }
}
