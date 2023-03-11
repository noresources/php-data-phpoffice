<?php

/**
 * Copyright © 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * #package Data
 */
namespace NoreSources\Data\Serialization;

use NoreSources\MediaType\MediaTypeInterface;
use NoreSources\Data\Serialization\Traits\MediaTypeListTrait;
use NoreSources\MediaType\MediaTypeFactory;
use NoreSources\MediaType\MediaType;
use NoreSources\Data\Serialization\Traits\DataFileSerializerTrait;
use NoreSources\Data\Serialization\Traits\DataFileUnserializerTrait;
use NoreSources\Data\Serialization\Traits\DataFileExtensionTrait;
use NoreSources\Container\Container;

/**
 * JSON content and file (de)serialization
 *
 * Supported media type parameters
 * - style=pretty
 *
 * Require json PHP extension.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc3986
 *
 */
class JsonSerializer implements DataUnserializerInterface,
	DataSerializerInterface, DataFileUnerializerInterface,
	DataFileSerializerInterface
{
	use MediaTypeListTrait;
	use DataFileSerializerTrait;
	use DataFileUnserializerTrait;
	use DataFileExtensionTrait;

	const STYLE_PRETTY = 'pretty';

	public function __construct()
	{
		$this->setFileExtensions([
			'json',
			'jsn'
		]);
	}

	public static function prerequisites()
	{
		return \extension_loaded('json');
	}

	public function getSerializableDataMediaTypes()
	{
		return $this->getMediaTypes();
	}

	public function canSerializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		if ($mediaType)
			return $this->matchMediaType($mediaType);

		return true;
	}

	public function getUnserializableDataMediaTypes()
	{
		return $this->getMediaTypes();
	}

	public function unserializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		return \json_decode($data, true);
	}

	public function serializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		$flags = 0;
		if ($mediaType)
		{
			if (\is_string($mediaType))
				$mediaType = MediaTypeFactory::createFromString(
					$mediaType);
			if (($mediaType instanceof MediaTypeInterface) &&
				($style = Container::keyValue(
					$mediaType->getParameters(), 'style')) &&
				(\strcasecmp($style, self::STYLE_PRETTY) == 0))
			{
				$flags |= JSON_PRETTY_PRINT;
			}
		}

		return \json_encode($data, $flags);
	}

	public function canUnserializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		if (!\is_string($data))
			return false;
		if ($mediaType)
			return $this->matchMediaType($mediaType);
		return true;
	}

	protected function matchMediaType(MediaTypeInterface $mediaType)
	{
		$syntax = $mediaType->getStructuredSyntax();
		return \is_string($syntax) && (\strcasecmp($syntax, 'json') == 0);
	}

	protected function buildMediaTypeList()
	{
		return [
			MediaTypeFactory::createFromString('application/json')
		];
	}
}
