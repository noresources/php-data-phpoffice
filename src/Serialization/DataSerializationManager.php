<?php
/**
 * Copyright © 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Data
 */
namespace NoreSources\Data\Serialization;

use NoreSources\Container\Stack;
use NoreSources\MediaType\MediaTypeInterface;
use NoreSources\MediaType\MediaTypeFactory;
use NoreSources\MediaType\MediaTypeException;
use NoreSources\MediaType\MediaType;
use NoreSources\Data\Serialization\Traits\DataFileMediaTypeNormalizerTrait;
use NoreSources\Type\TypeDescription;
use NoreSources\Data\Serialization\Traits\DataFileExtensionTrait;
use NoreSources\Data\Serialization\Traits\DataFileUnserializerTrait;

/**
 * Data(De)serializer aggregate
 */
class DataSerializationManager implements DataUnserializerInterface,
	DataSerializerInterface, DataFileUnerializerInterface,
	DataFileSerializerInterface
{
	use DataFileMediaTypeNormalizerTrait;

	/**
	 *
	 * @param boolean $registerBuiltins
	 *        	if TRUE, register all buil-tin serializers.
	 */
	public function __construct($registerBuiltins = true)
	{
		$this->stacks = [
			DataUnserializerInterface::class => new Stack(),
			DataSerializerInterface::class => new Stack(),
			DataFileUnerializerInterface::class => new Stack(),
			DataFileSerializerInterface::class => new Stack()
		];

		if ($registerBuiltins)
		{
			$this->registerSerializer(new IniSerializer());
			$this->registerSerializer(new CsvSerializer());
			$this->registerSerializer(new LuaSerializer());
			if (YamlSerializer::prerequisites())
				$this->registerSerializer(new YamlSerializer());
			if (JsonSerializer::prerequisites())
				$this->registerSerializer(new JsonSerializer());
		}
	}

	/**
	 * Add a (file|data) (de)serializer method.
	 *
	 * @param DataUnserializerInterface|DataSerializerInterface|DataFileUnerializerInterface|DataFileSerializerInterface $serializer
	 *        	(De)serializer to add
	 */
	public function registerSerializer($serializer)
	{
		foreach ($this->stacks as $classname => $stack)
		{
			/** @var Stack $stack */
			if (\is_a($serializer, $classname, true))
				$stack->push($serializer);
		}
	}

	public function getUnserializableDataMediaTypes()
	{
		$stack = $this->stacks[DataUnserializerInterface::class];
		$list = [];
		foreach ($stack as $serializer)
		{
			$l = $serializer->getUnserializableDataMediaTypes();
			foreach ($l as $s => $t)
			{
				if (!\is_string($s))
					$s = \strval($t);
				$list[$s] = $t;
			}
		}
		return $list;
	}

	public function canUnserializeData(MediaTypeInterface $mediaType)
	{
		$stack = $this->stacks[DataUnserializerInterface::class];
		foreach ($stack as $serializer)
		{
			if ($serializer->canUnserializeData($data, $mediaType))
				return true;
		}

		return false;
	}

	public function unserializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		$stack = $this->stacks[DataUnserializerInterface::class];
		foreach ($stack as $serializer)
		{
			if (!$serializer->canUnserializeData($data, $mediaType))
				continue;
			return $serialize->unserializeData($data, $mediaType);
		}
		throw new DataSerializationException();
	}

	public function getSerializableDataMediaTypes()
	{
		$stack = $this->stacks[DataSerializerInterface::class];
		$list = [];
		foreach ($stack as $serializer)
		{
			$l = $serializer->getSerializableDataMediaTypes();
			foreach ($l as $s => $t)
			{
				if (!\is_string($s))
					$s = \strval($t);
				$list[$s] = $t;
			}
		}
		return $list;
	}

	public function canSerializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		if (!$mediaType && $data instanceof \Serializable)
			return false;
		$stack = $this->stacks[DataSerializerInterface::class];
		foreach ($stack as $serializer)
		{
			if ($serializer->canSerializeData($data, $mediaType))
				return true;
		}

		return false;
	}

	public function serializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		if (!$mediaType && $data instanceof \Serializable)
			return $data->serialize();
		$stack = $this->stacks[DataSerializerInterface::class];
		foreach ($stack as $serializer)
		{
			if (!$serializer->canSerializeData($data, $mediaType))
				continue;
			return $serializer->serializeData($data, $mediaType);
		}

		throw new DataSerializationException();
	}

	public function getUnserializableFileMediaTypes()
	{
		$stack = $this->stacks[DataFileUnserializerTrait::class];
		$list = [];
		foreach ($stack as $serializer)
		{
			$l = $serializer->getUnserializableFileMediaTypes();
			foreach ($l as $s => $t)
			{
				if (!\is_string($s))
					$s = \strval($t);
				$list[$s] = $t;
			}
		}
		return $list;
	}

	public function canUnserializeFromFile($filename,
		MediaTypeInterface $mediaType = null)
	{
		$mediaType = $this->normalizeFileMediaType($filename, $mediaType);
		$stack = $this->stacks[DataFileUnerializerInterface::class];
		foreach ($stack as $serializer)
		{
			if ($serializer->canUnserializeFromFile($filename,
				$mediaType))
				return true;
		}

		return false;
	}

	public function unserializeFromFile($filename,
		MediaTypeInterface $mediaType = null)
	{
		$mediaType = $this->normalizeFileMediaType($filename, $mediaType);
		$stack = $this->stacks[DataFileUnerializerInterface::class];
		foreach ($stack as $serializer)
		{
			if (!$serializer->canUnserializeFromFile($filename,
				$mediaType))
				continue;

			return $serializer->unserializeFromFile($filename,
				$mediaType);
		}

		$name = ($mediaType) ? \strval($mediaType) : \pathinfo(
			$filename, PATHINFO_EXTENSION);
		throw new DataSerializationException(
			'No deserializer found for ' . $name . ' file');
	}

	public function getSerializableFileMediaTypes()
	{
		$stack = $this->stacks[DataFileSerializerInterface::class];
		$list = [];
		foreach ($stack as $serializer)
		{
			$l = $serializer->getSerializableFileMediaTypes();
			foreach ($l as $s => $t)
			{
				if (!\is_string($s))
					$s = \strval($t);
				$list[$s] = $t;
			}
		}
		return $list;
	}

	public function canSerializeToFile($filename, $data,
		MediaTypeInterface $mediaType = null)
	{
		$mediaType = $this->normalizeFileMediaType($filename, $mediaType,
			MediaTypeFactory::FROM_EXTENSION);
		$stack = $this->stacks[DataFileSerializerInterface::class];
		foreach ($stack as $serializer)
		{
			if ($serializer->canSerializeToFile($filename, $data,
				$mediaType))
				return true;
		}
		return false;
	}

	public function serializeToFile($filename, $data,
		MediaTypeInterface $mediaType = null)
	{
		$mediaType = $this->normalizeFileMediaType($filename, $mediaType,
			MediaTypeFactory::FROM_EXTENSION);
		$stack = $this->stacks[DataFileSerializerInterface::class];
		foreach ($stack as $serializer)
		{
			if (!$serializer->canSerializeToFile($filename, $data,
				$mediaType))
				continue;
			return $serializer->serializeToFile($filename, $data,
				$mediaType);
		}

		$name = ($mediaType) ? \strval($mediaType) : \pathinfo(
			$filename, PATHINFO_EXTENSION);
		throw new DataSerializationException(
			'No deserializer found for ' . $name . ' file');
		throw new DataSerializationException(
			'No serializer found for ' . $name . ' file');
	}

	/** @var Stack[] */
	private $stacks;
}
