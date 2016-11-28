# vakata\files\FileStorage
A file storage class used to move desired files to a location on disk.

## Methods

| Name | Description |
|------|-------------|
|[__construct](#vakata\files\filestorage__construct)|Create an instance.|
|[saveSettings](#vakata\files\filestoragesavesettings)|Save additional settings for a file.|
|[fromStream](#vakata\files\filestoragefromstream)|Store a stream.|
|[fromString](#vakata\files\filestoragefromstring)|Store a string in the file storage.|
|[fromFile](#vakata\files\filestoragefromfile)|Copy an existing file to the storage|
|[fromUpload](#vakata\files\filestoragefromupload)|Store an Upload instance.|
|[fromRequest](#vakata\files\filestoragefromrequest)|Store an upload from a Request object (supports chunked upload)|
|[get](#vakata\files\filestorageget)|Get a file's metadata from storage.|

---



### vakata\files\FileStorage::__construct
Create an instance.  


```php
public function __construct (  
    string $baseDirectory  
)   
```

|  | Type | Description |
|-----|-----|-----|
| `$baseDirectory` | `string` | the base directory to store files in |

---


### vakata\files\FileStorage::saveSettings
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


### vakata\files\FileStorage::fromStream
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


### vakata\files\FileStorage::fromString
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


### vakata\files\FileStorage::fromFile
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


### vakata\files\FileStorage::fromUpload
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


### vakata\files\FileStorage::fromRequest
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


### vakata\files\FileStorage::get
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

