<?php

/**
 * Copyright © 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * #package Data
 */
namespace NoreSources\Data\Serialization;

use NoreSources\Container\Container;
use NoreSources\Type\TypeConversion;
use NoreSources\MediaType\MediaTypeInterface;
use NoreSources\Data\Serialization\Traits\MediaTypeListTrait;
use NoreSources\MediaType\MediaTypeFactory;
use NoreSources\MediaType\MediaType;

/**
 * URL-encoded query parameter (de)serialization
 *
 * @see https://datatracker.ietf.org/doc/html/rfc3986
 */
class UrlEncodedSerializer implements DataUnserializerInterface,
	DataSerializerInterface
{
	use MediaTypeListTrait;

	public function getSerializableDataMediaTypes()
	{
		return $this->getMediaTypes();
	}

	public function canSerializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		if ($mediaType)
			return $this->matchMediaType($mediaType);
		return !\is_object($data) || Container::isTraversable($data);
	}

	public function getUnserializableDataMediaTypes()
	{
		return $this->getMediaTypes();
	}

	public function unserializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		if (\strpos($data, '=') !== false)
		{
			$params = [];
			\parse_str($data, $params);
			return $params;
		}

		return \urldecode($data);
	}

	public function serializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		if (Container::isArray($data))
			return \http_build_query(Container::createArray($data));

		return \urlencode(TypeConversion::toString($data));
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

	protected function buildMediaTypeList()
	{
		return [
			MediaType::createFromString(
				'application/x-www-form-urlencoded')
		];
	}
}
