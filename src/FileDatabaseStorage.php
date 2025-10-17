<?php

namespace vakata\files;

use vakata\database\DBInterface;

/**
 * An extension to the FileStorage class, which persists any data to a database.
 */
class FileDatabaseStorage extends FileStorage
{
    protected $db;
    protected $table;
    protected $reuse;

    /**
     * Create an instance
     * @param  string            $baseDirectory the base directory to store files in
     * @param  DBInterface       $db            a database connection instance to use for storage
     * @param  string            $table         optional table name to store to, defaults to `"uploads"`
     */
    public function __construct(
        string $baseDirectory,
        DBInterface $db,
        string $table = 'uploads',
        bool $reuse = false,
        ?string $prefix = null,
        ?string $tempDirectory = null
    ) {
        parent::__construct($baseDirectory, $prefix, $tempDirectory);
        $this->db = $db;
        $this->table = $table;
        $this->reuse = $reuse;
    }

    public function fromStream($handle, string $name): File
    {
        $file = parent::fromStream($handle, $name);
        if ($file->isComplete()) {
            $location = $file->id();
            if ($this->reuse && $file->hash() && $file->size()) {
                $existing = $this->db->one(
                    "SELECT location FROM {$this->table} WHERE hash = ? AND bytesize = ?",
                    [ $file->hash(), $file->size() ]
                );
                if ($existing !== null && is_file($this->baseDirectory . $existing)) {
                    unlink($this->baseDirectory . $file->id());
                    $location = $existing;
                }
            }
            $id = 0;
            $sql = "INSERT INTO {$this->table} (name, location, bytesize, uploaded, hash, settings) VALUES (?, ?, ?, ?, ?, ?)";
            $par = [
                $file->name(),
                $location,
                $file->size(),
                date('Y-m-d H:i:s', time()),
                $file->hash(),
                json_encode($file->settings(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ];
            if ($this->db->driverName() === 'oracle') {
                $sql .= " RETURNING id INTO ?";
                $par[] = &$id;
            }
            $q = $this->db->query($sql, $par);
            $id = $this->db->driverName() === 'oracle' ? $id : $q->insertId();
            unlink($this->baseDirectory . $file->id() . '.settings');
            return new File(
                $id,
                $file->name(),
                $file->hash(),
                $file->uploaded(),
                $file->size(),
                $file->settings(),
                true,
                $file->path()
            );
        }
        return $file;
    }

    public function get(string $id): File
    {
        $data = $this->db->one(
            "SELECT id, name, location, hash, bytesize, uploaded, settings FROM {$this->table} WHERE id = ?",
            $id
        );
        if (!$data) {
            throw new FileNotFoundException('File not found', 404);
        }
        if (!is_file($this->baseDirectory . $data['location'])) {
            throw new FileNotFoundException('File not found', 404);
        }
        return new File(
            $data['id'],
            $data['name'],
            $data['hash'],
            strtotime($data['uploaded']),
            $data['bytesize'],
            json_decode($data['settings'] ?? '[]', true),
            true,
            $this->baseDirectory . $data['location']
        );
    }

    public function set(File $file, $contents = null): File
    {
        $temp = $this->get($file->id());
        $this->db->query(
            "DELETE FROM {$this->table}_versions WHERE upload = ?",
            [$temp->id()]
        );
        if ($contents !== null) {
            $handle = fopen($temp->path(), 'w');
            while (!feof($contents)) {
                fwrite($handle, fread($contents, 4096));
            }
            fclose($handle);
        }
        $this->db->query(
            "UPDATE {$this->table} SET name = ?, bytesize = ?, uploaded = ?, hash = ?, settings = ? WHERE id = ?",
            [
                $file->name(),
                $file->size(),
                date('Y-m-d H:i:s', $file->uploaded()),
                $file->hash(),
                json_encode($file->settings()),
                $temp->id()
            ]
        );
        return $file;
    }
    public function getVersion(string $id, string $version): File
    {
        $data = $this->db->one(
            "SELECT id, name, location, hash, bytesize, uploaded, settings FROM {$this->table}_versions WHERE upload = ? AND version = ?",
            [ $id, $version ]
        );
        if (!$data) {
            throw new FileNotFoundException('File not found', 404);
        }
        if (!is_file($this->baseDirectory . $data['location'])) {
            throw new FileNotFoundException('File not found 2', 404);
        }
        return new File(
            $data['id'],
            $data['name'],
            $data['hash'],
            strtotime($data['uploaded']),
            $data['bytesize'],
            json_decode($data['settings'] ?? '[]', true),
            true,
            $this->baseDirectory . $data['location'],
            $id
        );
    }
    public function setVersion(string $id, string $version, string $contents): File
    {
        $temp = $this->get($id);
        file_put_contents($temp->path() . '.' . sha1($version), $contents);
        $this->db->begin();
        try {
            $this->db->query(
                "DELETE FROM {$this->table}_versions WHERE upload = ? AND version = ?",
                [$temp->id(), $version]
            );
            $this->db->query(
                "INSERT INTO {$this->table}_versions (upload, version, name, location, bytesize, uploaded, hash, settings)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $temp->id(),
                    $version,
                    $temp->name(),
                    str_replace($this->baseDirectory, '', $temp->path()) . '.' . sha1($version),
                    strlen($contents),
                    date('Y-m-d H:i:s', time()),
                    md5_file($temp->path() . '.' . sha1($version)),
                    json_encode($temp->settings(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ]
            );
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
        }
        return $this->getVersion($id, $version);
    }
}
