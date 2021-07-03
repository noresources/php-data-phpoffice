# ns-php-data

Data serialization library

## Features

Serialize/Unserialize content to/from
* JSON
* YAML
* CSV
* INI
* URL-encoded [application/x-www-form-urlencoded](https://datatracker.ietf.org/doc/html/rfc3986)

## Installation

```bash
composer require noresources/ns-php-data ~1.0
```

## Basic usage

```
use NoreSources\Data\Serialization\SerializationManager;

$serializer = SerializationManager::getInstance();
$data = $serializer->unserializeFromFile ('foo.json');
$serializer->serializeToFile ('bar.yaml');
```