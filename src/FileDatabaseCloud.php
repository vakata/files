<?php

namespace vakata\files;

use vakata\http\RequestInterface;
use vakata\database\DBInterface;

/**
 * An extension to the FileStorage class, which persists any data to a database along with the actual file data.
 */
class FileDatabaseCloud extends FileDatabaseStorage
{
    protected CloudInterface $cloud;

    public function __construct(
        CloudInterface $cloud,
        string $baseDirectory,
        DBInterface $db,
        $table = 'uploads'
    )
    {
        $this->cloud = $cloud;
        parent::__construct($baseDirectory, $db, $table, false, null, $baseDirectory);
    }

    protected function getLocation(string $name): string
    {
        $cnt = 0;
        // $uen = urlencode($name);
        $uen = sha1($name);
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
                $remote = $this->cloud->upload($handle, $location);
                fclose($handle);
                $this->db->query(
                    "UPDATE {$this->table} SET location = ?, data = ? WHERE id = ?",
                    [ $location, $remote, $file->id() ]
                );
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
            "SELECT id, name, hash, bytesize, uploaded, settings, location FROM {$this->table} WHERE id = ?",
            $id
        );
        if (!$data) {
            throw new FileNotFoundException('File not found', 404);
        }
        $loc = $data['location'];
        return new File(
            $data['id'],
            $data['name'],
            $data['hash'],
            strtotime($data['uploaded']),
            $data['bytesize'],
            json_decode($data['settings'] ?? '[]', true),
            true,
            function () use ($loc) {
                $name = tempnam($this->baseDirectory, "DWN");
                stream_copy_to_stream(
                    $this->cloud->stream($loc),
                    fopen($name, 'wb')
                );
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
            $location = $this->db->one("SELECT location FROM {$this->table} WHERE id = ?", $temp->id());
            $this->cloud->delete($location);
            $remote = $this->cloud->upload($contents, $location);
            $this->db->query(
                "UPDATE {$this->table} SET data = ? WHERE id = ?",
                [ $remote, $temp->id() ]
            );
        }
        return $this->get($temp->id());
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
        $loc = $data['location'];
        return new File(
            $data['id'],
            $data['name'],
            $data['hash'],
            strtotime($data['uploaded']),
            $data['bytesize'],
            json_decode($data['settings'] ?? '[]', true),
            true,
            function () use ($loc) {
                $name = tempnam($this->baseDirectory, "DWN");
                stream_copy_to_stream(
                    $this->cloud->stream($loc),
                    fopen($name, 'wb')
                );
                @register_shutdown_function(function () use ($name) { @unlink($name); });
                return $name;
            },
            $id
        );
    }
    public function setVersion(string $id, string $version, string $contents): File
    {
        $temp = $this->get($id);
        $file = tempnam($this->baseDirectory, "DWN");
        file_put_contents($file, $contents);
        $location = $this->db->one(
            "SELECT location FROM {$this->table} WHERE id = ?",
            $id
        );
        $location .= '.' . sha1($version);
        $handle = fopen($file, 'r');
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
                    $location,
                    strlen($contents),
                    date('Y-m-d H:i:s', time()),
                    md5($contents),
                    json_encode($temp->settings(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ]
            );
            $id = $this->db->one(
                "SELECT id FROM {$this->table}_versions WHERE upload = ? AND version = ?",
                [ $temp->id(), $version ]
            );
            try {
                $this->cloud->delete($location);
            } catch (\Throwable $ignore) {}
            $remote = $this->cloud->upload($handle, $location);
            $this->db->query(
                "UPDATE {$this->table}_versions SET data = ? WHERE id = ?",
                [ $remote, $id ]
            );
            fclose($handle);
            @unlink($file);
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollback();
            throw new FileException('Could not save file');
        }
        return $this->getVersion($temp->id(), $version);
    }
}
