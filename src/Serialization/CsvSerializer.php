<?php

/**
 * Copyright © 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * #package Data
 */
namespace NoreSources\Data\Serialization;

use NoreSources\Type\TypeConversion;
use NoreSources\MediaType\MediaTypeCompareTrait;
use NoreSources\MediaType\MediaTypeInterface;
use NoreSources\MediaType\MediaTypeFactory;
use NoreSources\Container\Container;
use NoreSources\MediaType\MediaTypeFileExtensionRegistry;
use NoreSources\Data\Serialization\Traits\DataFileMediaTypeNormalizerTrait;
use NoreSources\Data\Serialization\Traits\MediaTypeListTrait;
use NoreSources\Data\Serialization\Traits\DataFileExtensionTrait;
use NoreSources\Data\Serialization\Traits\DataFileUnserializerTrait;
use NoreSources\Data\Serialization\Traits\DataFileSerializerTrait;

/**
 * CSV (comma separated value) (de)serializer
 */
class CsvSerializer implements DataUnserializerInterface,
	DataSerializerInterface, DataFileUnerializerInterface,
	DataFileSerializerInterface
{

	use MediaTypeListTrait;
	use DataFileExtensionTrait;
	use DataFileSerializerTrait;
	use DataFileUnserializerTrait;

	public $separator = ',';

	public $enclosure = '"';

	public $escape = '\\';

	public function __construct()
	{
		$this->setFileExtensions([
			'csv'
		]);
	}

	public function getUnserializableDataMediaTypes()
	{
		return $this->getMediaTypes();
	}

	public function unserializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		$lines = \explode("\n", $data);
		$csv = [];
		foreach ($lines as $line)
		{
			if (empty($line))
				continue;
			$csv[] = \str_getcsv($line, $this->separator,
				$this->enclosure, $this->escape);
		}
		return $csv;
	}

	public function canUnserializeData(MediaTypeInterface $mediaType)
	{
		return $this->matchMediaType($mediaType);
	}

	public function getSerializableDataMediaTypes()
	{
		return $this->getMediaTypes();
	}

	public function canSerializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		$wrappers = \stream_get_wrappers();
		if (!Container::valueExists($wrappers, 'data'))
			return false;

		if ($mediaType)
			return $this->matchMediaType($mediaType);
		return true;
	}

	public function unserializeFromFile($filename,
		MediaTypeInterface $mediaType = null)
	{
		$stream = \fopen($filename, 'rb');
		if (!\is_resource($stream))
			throw new DataSerializationException(
				'Failed to open input stream');
		$lines = [];
		while ($line = \fgetcsv($stream, 0, $this->separator,
			$this->enclosure, $this->escape))
		{
			$lines[] = Container::map($line,
				function ($k, $v) {
					if (ctype_digit($v))
						return \intval($v);
					if (\is_numeric($v))
						return \float($v);
					return $v;
				});
		}
		return $lines;
	}

	public function serializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		$data = $this->prepareSerialization($data);
		$stream = fopen('data://text/csv,', 'rwb+');
		if (!\is_resource($stream))
			throw new DataSerializationException(
				'Failed to open data stream');

		foreach ($data as $line)
		{
			$result = \fputcsv($stream, $line, $this->separator,
				$this->enclosure, $this->escape);
			if ($result === false)
				throw new DataSerializationException(
					'Failed to write CSV line');
		}

		\fseek($stream, 0);
		$content = \stream_get_contents($stream);
		\fclose($stream);
		return $content;
	}

	public function serializeToFile($filename, $data,
		MediaTypeInterface $mediaType = null)
	{
		$data = $this->prepareSerialization($data);
		$stream = \fopen($filename, 'wb');
		if (!\is_resource($stream))
			throw new DataSerializationException(
				'Failed to open output file');
		foreach ($data as $line)
		{
			$line = Container::createArray($line, 0);
			$result = \fputcsv($stream, $line, $this->separator,
				$this->enclosure, $this->escape);
			if ($result === false)
				throw new DataSerializationException(
					'Failed to write CSV line');
		}
		\fclose($stream);
	}

	protected function prepareSerialization($data)
	{
		if (!\is_array($data))
			return [
				[
					$this->prepareFieldSerialization($data)
				]
			];

		if (Container::isAssociative($data))
		{
			$propertyList = [];
			foreach ($data as $key => $value)
			{
				$propertyList[] = [
					$key,
					$this->prepareFieldSerialization($value)
				];
			}

			return $propertyList;
		}

		$keys = [];
		$normalized = [];
		foreach ($data as $line)
		{
			if (!\is_array($line))
				$line = Container::isArray($line);

			$normalizedLine = [];
			foreach ($line as $key => $value)
			{
				if (!Container::keyExists($keys, $key))
					$keys[$key] = \count($keys);
				$normalizedLine[$keys[$key]] = $this->prepareFieldSerialization(
					$value);
				ksort($normalizedLine);
			}

			$normalized[] = $normalizedLine;
		}

		foreach ($normalized as $i => $line)
		{
			$changed = false;
			foreach ($keys as $key => $index)
			{
				if (Container::keyExists($line, $index))
					continue;
				$changed = true;
				$normalized[$i][$index] = '';
			}
			if ($changed)
				ksort($normalized[$i]);
		}

		$requireHeader = false;
		foreach ($keys as $k => $v)
		{
			if ($k != $v)
			{
				$requireHeader = true;
				break;
			}
		}

		if ($requireHeader)
		{
			$keys = \array_flip($keys);
			ksort($keys);
			array_unshift($normalized, $keys);
		}

		return $normalized;
	}

	protected function prepareFieldSerialization($data)
	{
		return TypeConversion::toString($data);
	}

	protected function buildMediaTypeList()
	{
		return [
			MediaTypeFactory::createFromString('text/csv'),
			/*
			 *  application/csv is not a registered media type but
			 *  finfo_type / mime_content_type may return this one
			 */
			MediaTypeFactory::createFromString('application/csv')
		];
	}
}
