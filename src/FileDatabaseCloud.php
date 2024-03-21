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

    public function __construct(CloudInterface $cloud, string $baseDirectory, DBInterface $db, $table = 'uploads')
    {
        $this->cloud = $cloud;
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
}
