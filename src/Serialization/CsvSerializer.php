<?php

/**
 * Copyright © 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * #package Data
 */
namespace NoreSources\Data\Serialization;

use NoreSources\MediaType\MediaType;
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
use NoreSources\Type\TypeConversionException;

/**
 * CSV (comma separated value) (de)serializer
 *
 * Supported media type parameters
 * - separator
 * - enclosure
 * - escape
 * - eol (unserializer and PHP 8.1+ for serializer)
 */
class CsvSerializer implements DataUnserializerInterface,
	DataSerializerInterface, DataFileUnerializerInterface,
	DataFileSerializerInterface
{

	use MediaTypeListTrait;
	use DataFileExtensionTrait;
	use DataFileSerializerTrait;
	use DataFileUnserializerTrait;

	/**
	 * Default field separator
	 *
	 * @var string
	 */
	public $separator = ',';

	/**
	 * Default field enclosure
	 *
	 * @var string
	 */
	public $enclosure = '"';

	/**
	 * Default escape character
	 *
	 * @var string
	 */
	public $escape = '\\';

	/**
	 * Default End of line
	 *
	 * @var string
	 */
	public $eol = "\n";

	public function __construct()
	{
		$this->setFileExtensions([
			'csv'
		]);
		$this->stringifier = [
			self::class,
			'defaultStringifier'
		];
	}

	/**
	 * Set the strigification function
	 *
	 * @param callable $callback
	 *        	Stringification function
	 * @throws \InvalidArgumentException
	 */
	public function setStringifier($callback)
	{
		if (!\is_callable($callback))
			throw new \InvalidArgumentException(
				'Stringifier must be a callable');
		$this->stringifier = $callback;
	}

	public function getUnserializableDataMediaTypes()
	{
		return $this->getMediaTypes();
	}

	public function unserializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		$separator = $enclosure = $escape = $eol = null;
		$this->retrieveParameters($separator, $enclosure, $escape, $eol,
			$mediaType);

		$lines = \explode($eol, $data);
		$csv = [];

		foreach ($lines as $line)
		{
			if (empty($line))
				continue;
			$csv[] = \str_getcsv($line, $separator, $enclosure, $escape);
		}
		return $csv;
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
		$separator = $enclosure = $escape = $eol = null;
		$this->retrieveParameters($separator, $enclosure, $escape, $eol,
			$mediaType);
		$stream = @\fopen($filename, 'rb');
		if (!\is_resource($stream))
		{
			$error = \error_get_last();
			throw new DataSerializationException(
				'Failed to open input stream: ' . $error['message']);
		}
		$lines = [];
		while ($line = @\fgetcsv($stream, 0, $separator, $enclosure,
			$escape))
		{
			$lines[] = Container::map($line,
				function ($k, $v) {
					if (ctype_digit($v))
						return \intval($v);
					if (\is_numeric($v))
						return \floatval($v);
					return $v;
				});
		}
		return $lines;
	}

	public function serializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		$data = $this->prepareSerialization($data);
		$stream = fopen('php://memory', 'w');
		if (!\is_resource($stream))
		{
			$error = \error_get_last();
			throw new DataSerializationException(
				'Failed to open data stream: ' . $error['message']);
		}

		$this->writeLinesToStream($stream, $data, $mediaType);
		@\fseek($stream, 0);
		$content = \stream_get_contents($stream);
		@\fclose($stream);
		return $content;
	}

	public function serializeToFile($filename, $data,
		MediaTypeInterface $mediaType = null)
	{
		$data = $this->prepareSerialization($data);
		$stream = @\fopen($filename, 'wb');
		if (!\is_resource($stream))
		{
			$error = \error_get_last();
			throw new DataSerializationException(
				'Failed to open output file: ' . $error['message']);
		}
		$this->writeLinesToStream($stream, $data, $mediaType);
		@\fclose($stream);
	}

	public static function defaultStringifier($value)
	{
		try
		{
			return TypeConversion::toString($value);
		}
		catch (TypeConversionException $e)
		{}
		return serialize($value);
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
				$line = Container::isTraversable($line) ? Container::createArray(
					$line) : [
					$line
				];

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
		return \call_user_func($this->stringifier, $data);
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

	protected function retrieveParameters(&$separator, &$enclosure,
		&$escape, &$eol, $mediaType)
	{
		foreach ([
			'separator',
			'enclosure',
			'escape',
			'eol'
		] as $v)
		{
			$$v = $this->$v;
			if ($mediaType instanceof MediaTypeInterface)
				$$v = Container::keyValue($mediaType->getParameters(),
					$v, $$v);
		}
	}

	protected function writeLinesToStream($stream, $lines, $mediaType)
	{
		$separator = $enclosure = $escape = $eol = null;
		$this->retrieveParameters($separator, $enclosure, $escape, $eol,
			$mediaType);
		foreach ($lines as $line)
		{
			$args = [
				$stream,
				$line,
				$separator,
				$enclosure
			];
			if (version_compare(PHP_VERSION, '8.1.0', '>='))
				$args[] = $eol;

			$result = @\call_user_func_array('\fputcsv', $args);
			if ($result === false)
			{
				$error = \error_get_last();
				throw new DataSerializationException(
					'Failed to write CSV line: ' . $error['message']);
			}
		}
	}

	/**
	 *
	 * @var callable
	 */
	private $stringifier;
}
