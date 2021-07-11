<?php

/**
 * Copyright © 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * #package Data
 */
namespace NoreSources\Data\Serialization;

use NoreSources\MediaType\MediaTypeInterface;
use NoreSources\Data\Serialization\Traits\DataFileExtensionTrait;
use NoreSources\Data\Serialization\Traits\DataFileUnserializerTrait;
use NoreSources\Data\Serialization\Traits\MediaTypeListTrait;
use NoreSources\MediaType\MediaType;
use NoreSources\MediaType\MediaTypeFactory;

/**
 * INI deserialization.
 */
class IniSerializer implements DataUnserializerInterface,
	DataFileUnerializerInterface
{
	use MediaTypeListTrait;
	use DataFileUnserializerTrait;
	use DataFileExtensionTrait;

	public function __construct()
	{
		$this->setFileExtensions([
			'ini'
		]);
	}

	public function getUnserializableDataMediaTypes()
	{
		return $this->getMediaTypes();
	}

	/**
	 * Note: Guessing ini media type from file content type is unreliable
	 */
	public function canUnserializeFromFile($filename,
		MediaTypeInterface $mediaType = null)
	{
		$mediaType = $this->normalizeFileMediaType(null, $mediaType);
		if ($mediaType)
			return $this->canUnserializeData($mediaType);
		return $this->matchExtension($filename);
	}

	public function unserializeFromFile($filename,
		MediaTypeInterface $mediaType = null)
	{
		$data = @parse_ini_file($filename, true);
		if ($data === false)
		{
			$error = \error_get_last();
			throw new DataSerializationException($error['message']);
		}
		return $data;
	}

	public function unserializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		$data = @parse_ini_string($data, true);
		if ($data === false)
		{
			$error = \error_get_last();
			throw new DataSerializationException($error['message']);
		}
		return $data;
	}

	public function getUnserializableFileMediaTypes()
	{
		return $this->getMediaTypes();
	}

	public function canUnserializeData(MediaTypeInterface $mediaType)
	{
		return $this->matchMediaType($mediaType);
	}

	protected function matchMediaType(MediaTypeInterface $mediaType)
	{
		$types = $this->getMediaTypes();
		$s = \strval($mediaType);
		foreach ($types as $type)
		{
			if (\strcasecmp(\strval($type), $s) == 0)
				return true;
		}

		$syntax = $mediaType->getStructuredSyntax();
		return \is_string($syntax) && (\strcasecmp($syntax, 'ini') == 0);
	}

	protected function buildMediaTypeList()
	{
		return [
			MediaType::fromString('text/x-ini')
		];
	}

	protected function getMediaTypeFactoryFlags()
	{
		return MediaTypeFactory::FROM_ALL |
			MediaTypeFactory::FROM_EXTENSION_FIRST;
	}
}
