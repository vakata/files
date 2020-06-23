<?php

namespace vakata\files;

use vakata\http\RequestInterface;
use vakata\database\DBInterface;

/**
 * An extension to the FileStorage class, which persists any data to a database along with the actual file data.
 */
class FileDatabase extends FileDatabaseStorage
{
    public function __construct($baseDirectory, DBInterface $db, $table = 'uploads')
    {
        parent::__construct($baseDirectory, $db, $table, false, null, $baseDirectory);
        $this->prefix = '';
    }

    public function fromStream($handle, $name): File
    {
        $file = parent::fromStream($handle, $name);

        if ($file->isComplete()) {
            try {
                $handle = $file->content();
                // update
                if ($this->db->driverName() === 'oracle') {
                    $trans = $this->db->begin();
                    $this->db->query(
                        "UPDATE {$this->table} SET uploaded = ?, data = EMPTY_BLOB() WHERE id = ? RETURNING data INTO ?",
                        [ date('Y-m-d H:i:s'), $file->id(), $handle ]
                    );
                    $this->db->commit($trans);
                } else {
                    $this->db->query(
                        "UPDATE {$this->table} SET data = ? WHERE id = ?",
                        [ $handle, $file->id() ]
                    );
                }
                fclose($handle);
                if (strpos($file->path(), $this->baseDirectory) === 0) {
                    @unlink($file->path());
                }
            } catch (\Exception $e) {
                throw new FileException('Could not save file');
            }
        }

        return $this->get($file->id());
    }
    public function get(string $id): File
    {
        $data = $this->db->one(
            "SELECT id, name, hash, bytesize, uploaded, settings FROM {$this->table} WHERE id = ?",
            $id
        );
        if (!$data) {
            throw new FileNotFoundException('File not found', 404);
        }
        return new File(
            $data['id'],
            $data['name'],
            $data['hash'],
            strtotime($data['uploaded']),
            $data['bytesize'],
            json_decode($data['setttings'], true),
            true,
            function () use ($id) {
                $name = tempnam($this->baseDirectory, "DWN");
                file_put_contents($name, $this->db->one("SELECT data FROM {$this->table} WHERE id = ?", $id));
                @register_shutdown_function(function () use ($name) { @unlink($name); });
                return $name;
            }
        );
    }
}
