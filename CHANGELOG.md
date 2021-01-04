# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2021-01-03

### Changed

- Changed minimum PHP version to 7.3
- Change minimum PHPUnit to 9.

### Removed

- Removed save method

## [1.3.0] - 2020-11-16

### Adeded 

- Added zip::close, the save method is misleading because ZipArchive calls close even if you don't.

## [1.2.0] - 2020-11-12

### Added 

- Added zip::get

## [1.1.0] - 2020-11-12

### Added

- Added zip::rename

## [1.0.1] - 2020-06-04

### Fixed
- Fixed issue running on new versions of PHP with operating system using older ZLIB.

## [1.0.0] - 2020-01-15

Initial release