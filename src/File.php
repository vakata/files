<?php

namespace vakata\files;

class File
{
    protected $id;
    protected $name;
    protected $hash;
    protected $uploaded;
    protected $size;
    protected $settings;
    protected $complete;
    protected $location;
    protected $parent;

    public function __construct(
        string $id,
        string $name,
        string $hash,
        int $uploaded,
        int $size,
        ?array $settings = null,
        bool $complete = true,
        $location = null,
        $parent = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->hash = $hash;
        $this->uploaded = $uploaded;
        $this->size = $size;
        $this->settings = $settings ?? [];
        $this->complete = $complete;
        $this->location = $location;
        $this->parent = $parent;
    }

    public function id(): string
    {
        return $this->id;
    }
    public function name(): string
    {
        return $this->name;
    }
    public function hash(): string
    {
        return $this->hash;
    }
    public function uploaded(): int
    {
        return $this->uploaded;
    }
    public function size(): int
    {
        return $this->size;
    }
    public function settings(): array
    {
        return $this->settings;
    }
    public function setting(string $key): mixed
    {
        return $this->settings[$key] ?? null;
    }
    public function isComplete(): bool
    {
        return $this->complete;
    }
    public function parent(): mixed
    {
        return $this->parent;
    }
    public function isVersion(): bool
    {
        return $this->parent !== null;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
    public function setUploaded(int $uploaded): self
    {
        $this->uploaded = $uploaded;
        return $this;
    }
    public function setSettings(array $settings): self
    {
        $this->settings = $settings;
        return $this;
    }
    public function setSetting(string $k, $v): self
    {
        $this->settings[$k] = $v;
        return $this;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;
        return $this;
    }
    public function setHash(string $hash): self
    {
        $this->hash = $hash;
        return $this;
    }
    public function setParent(mixed $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    public function __get($k)
    {
        if (in_array($k, [ 'id', 'name', 'hash', 'uploaded', 'size', 'settings', 'parent' ])) {
            return $this->{$k}();
        }
        return $this->setting($k);
    }
    public function __set($k, $v)
    {
        if ($k === 'name') {
            $this->setName($v);
        } elseif ($k === 'uploaded') {
            $this->setUploaded($v);
        } elseif ($k === 'settings') {
            $this->setSettings($v);
        } else {
            $this->setSetting($k, $v);
        }
    }

    public function isLocal(): bool
    {
        return isset($this->location) && is_string($this->location);
    }
    public function path(): ?string
    {
        if (is_callable($this->location)) {
            $this->location = call_user_func($this->location);
        }
        return $this->location;
    }
    public function content(bool $asString = false)
    {
        if (!$this->path()) {
            throw new FileException('No file data');
        }
        return $asString ? file_get_contents($this->path()) : fopen($this->path(), 'r');
    }
    public function ext(): string
    {
        return strtolower(array_reverse(explode('.', $this->name()))[0] ?? '');
    }
}
