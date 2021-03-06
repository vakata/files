# files

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Code Climate][ico-cc]][link-cc]
[![Tests Coverage][ico-cc-coverage]][link-cc]

File storage classes.

## Install

Via Composer

``` bash
$ composer require vakata/files
```

## Usage

``` php
// create an instance
$files = new \vakata\files\FileStorage('/path/to/dir');
$file = $files->fromFile('/path/to/existing/file'); // stores the file
$files->get($file['id']); // retrieves the file meta data
$file = $files->fromString('save this string', 'into.file.name');
$files->get($file['id']); // retrieves the file meta data
```

Read more in the [API docs](docs/README.md)

## Testing

``` bash
$ composer test
```


## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email github@vakata.com instead of using the issue tracker.

## Credits

- [vakata][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information. 

[ico-version]: https://img.shields.io/packagist/v/vakata/files.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/vakata/files/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/vakata/files.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/vakata/files.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/vakata/files.svg?style=flat-square
[ico-cc]: https://img.shields.io/codeclimate/github/vakata/files.svg?style=flat-square
[ico-cc-coverage]: https://img.shields.io/codeclimate/coverage/github/vakata/files.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/vakata/files
[link-travis]: https://travis-ci.org/vakata/files
[link-scrutinizer]: https://scrutinizer-ci.com/g/vakata/files/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/vakata/files
[link-downloads]: https://packagist.org/packages/vakata/files
[link-author]: https://github.com/vakata
[link-contributors]: ../../contributors
[link-cc]: https://codeclimate.com/github/vakata/files

