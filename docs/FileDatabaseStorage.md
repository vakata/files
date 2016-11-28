# vakata\files\FileDatabaseStorage
An extension to the FileStorage class, which persists any data to a database.

## Methods

| Name | Description |
|------|-------------|
|[__construct](#vakata\files\filedatabasestorage__construct)|Create an instance|
|[saveSettings](#vakata\files\filedatabasestoragesavesettings)|Save additional settings for a file.|
|[fromStream](#vakata\files\filedatabasestoragefromstream)|Store a stream.|
|[get](#vakata\files\filedatabasestorageget)|Get a file's metadata from storage.|
|[fromString](#vakata\files\filedatabasestoragefromstring)|Store a string in the file storage.|
|[fromFile](#vakata\files\filedatabasestoragefromfile)|Copy an existing file to the storage|
|[fromUpload](#vakata\files\filedatabasestoragefromupload)|Store an Upload instance.|
|[fromRequest](#vakata\files\filedatabasestoragefromrequest)|Store an upload from a Request object (supports chunked upload)|

---



### vakata\files\FileDatabaseStorage::__construct
Create an instance  


```php
public function __construct (  
    string $baseDirectory,  
    \DatabaseInterface $db,  
    string $table  
)   
```

|  | Type | Description |
|-----|-----|-----|
| `$baseDirectory` | `string` | the base directory to store files in |
| `$db` | `\DatabaseInterface` | a database connection instance to use for storage |
| `$table` | `string` | optional table name to store to, defaults to `"uploads"` |

---


### vakata\files\FileDatabaseStorage::saveSettings
Save additional settings for a file.  


```php
public function saveSettings (  
    int|string|array $file,  
    mixed $settings  
) : array    
```

|  | Type | Description |
|-----|-----|-----|
| `$file` | `int`, `string`, `array` | file id or array |
| `$settings` | `mixed` | data to store for the file |
|  |  |  |
| `return` | `array` | the file array (as from getFile) |

---


### vakata\files\FileDatabaseStorage::fromStream
Store a stream.  


```php
public function fromStream (  
    resource $handle,  
    string $name,  
    mixed $settings  
) : array    
```

|  | Type | Description |
|-----|-----|-----|
| `$handle` | `resource` | the stream to read and store |
| `$name` | `string` | the name to use for the stream |
| `$settings` | `mixed` | optional data to save along with the file |
|  |  |  |
| `return` | `array` | an array consisting of the ID, name, path, hash and size of the copied file |

---


### vakata\files\FileDatabaseStorage::get
Get a file's metadata from storage.  


```php
public function get (  
    mixed $id,  
    bool $contents  
) : array    
```

|  | Type | Description |
|-----|-----|-----|
| `$id` | `mixed` | the file ID to return |
| `$contents` | `bool` | should the result include the file path, defaults to false |
|  |  |  |
| `return` | `array` | an array consisting of the ID, name, path, hash and size of the file |

---


### vakata\files\FileDatabaseStorage::fromString
Store a string in the file storage.  


```php
public function fromString (  
    string $content,  
    string $name,  
    mixed $settings  
) : array    
```

|  | Type | Description |
|-----|-----|-----|
| `$content` | `string` | the string to store |
| `$name` | `string` | the name to store the string under |
| `$settings` | `mixed` | optional data to save along with the file |
|  |  |  |
| `return` | `array` | an array consisting of the ID, name, path, hash and size of the copied file |

---


### vakata\files\FileDatabaseStorage::fromFile
Copy an existing file to the storage  


```php
public function fromFile (  
    string $path,  
    string $name,  
    mixed $settings  
) : array    
```

|  | Type | Description |
|-----|-----|-----|
| `$path` | `string` | the path to the file |
| `$name` | `string` | the optional name to store the string under (defaults to the current file name) |
| `$settings` | `mixed` | optional data to save along with the file |
|  |  |  |
| `return` | `array` | an array consisting of the ID, name, path, hash and size of the copied file |

---


### vakata\files\FileDatabaseStorage::fromUpload
Store an Upload instance.  


```php
public function fromUpload (  
    \UploadInterface $upload,  
    string|null $name,  
    mixed $settings  
) : array    
```

|  | Type | Description |
|-----|-----|-----|
| `$upload` | `\UploadInterface` | the instance to store |
| `$name` | `string`, `null` | an optional name to store under (defaults to the Upload name) |
| `$settings` | `mixed` | optional data to save along with the file |
|  |  |  |
| `return` | `array` | an array consisting of the ID, name, path, hash and size of the copied file |

---


### vakata\files\FileDatabaseStorage::fromRequest
Store an upload from a Request object (supports chunked upload)  


```php
public function fromRequest (  
    \RequestInterface $request,  
    string $key,  
    string|null $name,  
    mixed $settings  
) : array    
```

|  | Type | Description |
|-----|-----|-----|
| `$request` | `\RequestInterface` | the request to process |
| `$key` | `string` | optional upload key, defaults to `"file"` |
| `$name` | `string`, `null` | an optional name to store under (defaults to the upload name or the post field) |
| `$settings` | `mixed` | optional data to save along with the file |
|  |  |  |
| `return` | `array` | an array consisting of the ID, name, path, hash and size of the copied file |

---

