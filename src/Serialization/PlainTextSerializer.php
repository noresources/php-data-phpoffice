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
use NoreSources\Type\TypeConversion;
use NoreSources\Container\Container;

/**
 * Plain text serialization
 */
class PlainTextSerializer implements DataUnserializerInterface,
	DataSerializerInterface, DataFileUnerializerInterface,
	DataFileSerializerInterface
{
	use MediaTypeListTrait;
	use DataFileSerializerTrait;
	use DataFileUnserializerTrait;
	use DataFileExtensionTrait;

	public function __construct()
	{
		$this->setFileExtensions([
			'txt',
			'plain'
		]);
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
		$list = [];
		$crlf = \explode("\r\n", TypeConversion::toString($data));

		foreach ($crlf as $a)
		{
			$cr = \explode("\r", $a);
			foreach ($cr as $b)
			{
				$lf = \explode("\n", $b);
				foreach ($lf as $value)
				{
					if (\is_numeric($value))
					{
						if (\ctype_digit($value))
							$value = TypeConversion::toInteger($value);
						else
							$value = TypeConversion::toFloat($value);
					}
					$list[] = $value;
				}
			}
		}
		$c = \count($list);
		if ($c == 0)
			return '';
		elseif ($c == 1)
			return $list[0];
		return $list;
	}

	public function serializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		if (!Container::isTraversable($data))
		{
			return TypeConversion::toString($data);
		}

		$lines = [];

		foreach ($data as $value)
		{
			$visited = [];
			$this->recursiveSerializeData($lines, $visited, $value,
				$mediaType);
		}
		return \implode("\n", $lines);
	}

	public function canUnserializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		if ($mediaType)
			return $this->matchMediaType($mediaType);
		return true;
	}

	public function recursiveSerializeData(&$lines, &$visited, $data,
		MediaTypeInterface $mediaType = null)
	{
		if (\in_array($data, $visited))
			return;

		if (!Container::isTraversable($data))
		{
			\array_push($lines, TypeConversion::toString($data));
			return;
		}

		$visited[] = $data;
		foreach ($data as $value)
		{
			$this->recursiveSerializeData($lines, $visited, $value,
				$mediaType);
		}
	}

	protected function buildMediaTypeList()
	{
		return [
			MediaTypeFactory::createFromString('text/plain')
		];
	}
}
