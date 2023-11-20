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
    }

    protected function getLocation(string $name): string
    {
        $cnt = 0;
        $uen = urlencode($name);
        do {
            $newName = sprintf('%04d', $cnt++) . '.' . $uen;
        } while ($this->db->one("SELECT 1 FROM {$this->table} WHERE location = ?", $this->prefix . $newName));

        return $this->prefix . $newName;
    }

    public function fromStream($handle, $name): File
    {
        $file = parent::fromStream($handle, $name);
        $location = $this->getLocation($name);

        if ($file->isComplete()) {
            try {
                $handle = $file->content();
                // update
                if ($this->db->driverName() === 'oracle') {
                    $this->db->begin();
                    $this->db->query(
                        "UPDATE {$this->table} SET location = ?, uploaded = ?, data = EMPTY_BLOB() WHERE id = ? RETURNING data INTO ?",
                        [ $location, date('Y-m-d H:i:s'), $file->id(), $handle ]
                    );
                    $this->db->commit();
                } elseif ($this->db->driverName() === 'postgre') {
                    $this->db->begin();
                    $this->db->query(
                        "UPDATE {$this->table} SET location = ?, data = decode(?, 'hex') WHERE id = ?",
                        [ $location, bin2hex(''), $file->id() ]
                    );
                    while (!feof($handle)) {
                        $this->db->query(
                            "UPDATE {$this->table} SET data = data || decode(?, 'hex') WHERE id = ?",
                            [ bin2hex(fread($handle, 500000)), $file->id() ]
                        );
                    }
                    $this->db->commit();
                } else {
                    $this->db->query(
                        "UPDATE {$this->table} SET location = ?, data = ? WHERE id = ?",
                        [ $location, $handle, $file->id() ]
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
            json_decode($data['settings'] ?? '[]', true),
            true,
            function () use ($id) {
                $name = tempnam($this->baseDirectory, "DWN");
                file_put_contents($name, '');
                $i = 1;
                $chunk = 100000;
                while (true) {
                    $data = $this->db->one(
                        "SELECT SUBSTRING(data FROM {$i} FOR {$chunk}) FROM {$this->table} WHERE id = ?",
                        $id
                    );
                    if ($this->db->driverName() === 'postgre') {
                        $data = hex2bin(substr($data, 2));
                    }
                    file_put_contents($name, $data, FILE_APPEND);
                    $i += $chunk;
                    if (strlen($data) < $chunk) {
                        break;
                    }
                }
                @register_shutdown_function(function () use ($name) { @unlink($name); });
                return $name;
            }
        );
    }
    public function set(File $file, $contents = null): File
    {
        parent::set($file, null);
        $temp = $this->get($file->id());
        if ($contents !== null) {
            if ($this->db->driverName() === 'oracle') {
                $this->db->begin();
                $this->db->query(
                    "UPDATE {$this->table} SET uploaded = ?, data = EMPTY_BLOB() WHERE id = ? RETURNING data INTO ?",
                    [ date('Y-m-d H:i:s'), $temp->id(), $contents ]
                );
                $this->db->commit();
            } elseif ($this->db->driverName() === 'postgre') {
                $this->db->begin();
                $this->db->query(
                    "UPDATE {$this->table} SET data = decode(?, 'hex') WHERE id = ?",
                    [ bin2hex(''), $temp->id() ]
                );
                while (!feof($contents)) {
                    $this->db->query(
                        "UPDATE {$this->table} SET data = data || decode(?, 'hex') WHERE id = ?",
                        [ bin2hex(fread($contents, 500000)), $temp->id() ]
                    );
                }
                $this->db->commit();
            } else {
                $this->db->query(
                    "UPDATE {$this->table} SET data = ? WHERE id = ?",
                    [ $contents, $temp->id() ]
                );
            }
        }
        return $file;
    }
}
