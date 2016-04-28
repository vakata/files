# vakata\files\FileStorage
A file storage class usedd to move desired files to a location on disk.

## Methods

| Name | Description |
|------|-------------|
|[__construct](#vakata\files\filestorage__construct)|Create an instance.|
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


### vakata\files\FileStorage::fromStream
Store a stream.  


```php
public function fromStream (  
    \stream $handle,  
    string $name  
) : array    
```

|  | Type | Description |
|-----|-----|-----|
| `$handle` | `\stream` | the stream to read and store |
| `$name` | `string` | the name to use for the stream |
|  |  |  |
| `return` | `array` | an array consisting of the ID, name, path, hash and size of the copied file |

---


### vakata\files\FileStorage::fromString
Store a string in the file storage.  


```php
public function fromString (  
    string $content,  
    string $name  
) : array    
```

|  | Type | Description |
|-----|-----|-----|
| `$content` | `string` | the string to store |
| `$name` | `string` | the name to store the string under |
|  |  |  |
| `return` | `array` | an array consisting of the ID, name, path, hash and size of the copied file |

---


### vakata\files\FileStorage::fromFile
Copy an existing file to the storage  


```php
public function fromFile (  
    string $path,  
    string $name  
) : array    
```

|  | Type | Description |
|-----|-----|-----|
| `$path` | `string` | the path to the file |
| `$name` | `string` | the optional name to store the string under (defaults to the current file name) |
|  |  |  |
| `return` | `array` | an array consisting of the ID, name, path, hash and size of the copied file |

---


### vakata\files\FileStorage::fromUpload
Store an Upload instance.  


```php
public function fromUpload (  
    \UploadInterface $upload,  
    string|null $name  
) : array    
```

|  | Type | Description |
|-----|-----|-----|
| `$upload` | `\UploadInterface` | the instance to store |
| `$name` | `string`, `null` | an optional name to store under (defaults to the Upload name) |
|  |  |  |
| `return` | `array` | an array consisting of the ID, name, path, hash and size of the copied file |

---


### vakata\files\FileStorage::fromRequest
Store an upload from a Request object (supports chunked upload)  


```php
public function fromRequest (  
    \RequestInterface $request,  
    string $key,  
    string|null $name  
) : array    
```

|  | Type | Description |
|-----|-----|-----|
| `$request` | `\RequestInterface` | the request to process |
| `$key` | `string` | optional upload key, defaults to `"file"` |
| `$name` | `string`, `null` | an optional name to store under (defaults to the upload name or the post field) |
|  |  |  |
| `return` | `array` | an array consisting of the ID, name, path, hash and size of the copied file |

---


### vakata\files\FileStorage::get
Get a file's metadata from storage.  


```php
public function get (  
    mixed $id  
) : array    
```

|  | Type | Description |
|-----|-----|-----|
| `$id` | `mixed` | the file ID to return |
|  |  |  |
| `return` | `array` | an array consisting of the ID, name, path, hash and size of the file |

---

