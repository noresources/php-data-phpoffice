<?php

/**
 * Copyright © 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * #package Data
 */
namespace NoreSources\Data\Serialization;

use NoreSources\MediaType\MediaType;
use NoreSources\Data\Serialization\Traits\DataFileMediaTypeNormalizerTrait;
use NoreSources\Data\Serialization\Traits\DataFileExtensionTrait;
use NoreSources\Data\Serialization\Traits\DataFileUnserializerTrait;
use NoreSources\MediaType\MediaTypeInterface;
use NoreSources\Data\Serialization\Traits\MediaTypeListTrait;
use NoreSources\MediaType\MediaTypeFactory;
use function Composer\Autoload\includeFile;

/**
 * Load data from a PHP "module" file that returns data.
 *
 * For security reason, this serializer will not be available by default
 * with the DataSerializationManager.
 *
 * ATTENTION Never use this with untrusted files.
 */
class PhpFileUnserializer implements DataFileUnerializerInterface
{
	use MediaTypeListTrait;
	use DataFileMediaTypeNormalizerTrait;
	use DataFileExtensionTrait;

	public function canUnserializeFromFile($filename,
		MediaTypeInterface $mediaType = null)
	{
		$mediaType = $this->normalizeFileMediaType($filename, $mediaType);
		if ($mediaType)
			return $this->matchMediaType($mediaType);
		return false;
	}

	public function getUnserializableFileMediaTypes()
	{
		return $this->getMediaTypes();
	}

	public function unserializeFromFile($filename,
		MediaTypeInterface $mediaType = null)
	{
		$sandbox = new PhpFileUnserializerSandbox();
		return $sandbox($filename);
	}

	public function __construct()
	{
		$this->setFileExtensions([
			'php'
		]);
	}

	protected function buildMediaTypeList()
	{
		return [
			MediaType::createFromString('text/x-php')
		];
	}
}

class PhpFileUnserializerSandbox
{

	public function __invoke($filename)
	{
		$data = null;
		$error = null;

		if (!\file_exists($filename))
			throw new DataSerializationException(
				$filename . ' not found');
		$previous = set_error_handler(
			function ($errno, $message, $file, $line) use (&$error) {
				if (!(error_reporting() & $errno))
					return false;
				$error = $message;
			});
		try
		{
			$data = require ($filename);
		}
		catch (\Exception $e)
		{
			$error = $e->getMessage();
		}

		\set_error_handler($previous);

		if ($error)
			throw new DataSerializationException($error);
		return $data;
	}
}
