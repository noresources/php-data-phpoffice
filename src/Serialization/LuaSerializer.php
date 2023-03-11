<?php

/**
 * Copyright © 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * #package Data
 */
namespace NoreSources\Data\Serialization;

use NoreSources\MediaType\MediaType;
use NoreSources\Container\Container;
use NoreSources\Type\TypeConversion;
use NoreSources\MediaType\MediaTypeInterface;
use NoreSources\Data\Serialization\Traits\MediaTypeListTrait;
use NoreSources\MediaType\MediaTypeFactory;
use NoreSources\Data\Serialization\Traits\DataFileSerializerTrait;

/**
 * Lua primitive serialization
 *
 * @see https://datatracker.ietf.org/doc/html/rfc3986
 */
class LuaSerializer implements DataSerializerInterface,
	DataFileSerializerInterface
{
	use MediaTypeListTrait;
	use DataFileSerializerTrait;

	/**
	 * Export value "as is"
	 *
	 * This is the default behavior of the serializeData() method
	 *
	 * @var string
	 */
	const MODE_RAW = 'raw';

	/**
	 * Export value prefixed by a "return" keyword
	 *
	 * This is the default behavior of the serializeDataToFile() method
	 *
	 * @var string
	 */
	const MODE_MODULE = 'module';

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

	public function serializeData($data,
		MediaTypeInterface $mediaType = null)
	{
		$prefix = '';
		if ($mediaType)
		{
			if (\is_string($mediaType))
				$mediaType = MediaTypeFactory::createFromMedia(
					$mediaType);

			if (($mediaType instanceof MediaTypeInterface) &&
				($mode = Container::keyValue(
					$mediaType->getParameters(), 'mode')) &&
				(\strcasecmp($mode, self::MODE_MODULE) == 0))
			{
				$prefix = 'return ';
			}
		}

		if (Container::isTraversable($data))
		{
			return $prefix . $this->serializeTable($data);
		}

		return $prefix . $this->serializeLiteral($data);
	}

	public function serializeToFile($filename, $data,
		MediaTypeInterface $mediaType = null)
	{
		$serialized = '';
		$prefix = 'return ';
		if ($mediaType)
		{
			if (\is_string($mediaType))
				$mediaType = MediaTypeFactory::createFromMedia(
					$mediaType);

			if (($mediaType instanceof MediaTypeInterface) &&
				($mode = Container::keyValue(
					$mediaType->getParameters(), 'mode')) &&
				(\strcasecmp($mode, self::MODE_RAW) == 0))
			{
				$prefix = '';
			}
		}

		if (Container::isTraversable($data))
			$serialized = $prefix . $this->serializeTable($data);
		else
			$serialized = $prefix . $this->serializeLiteral($data);

		$flags = 0;
		if (\is_string($filename))
		{
			if (!filter_var($filename, FILTER_VALIDATE_URL))
				$flags = LOCK_EX;
		}

		\file_put_contents($filename, $serialized, $flags);
	}

	protected function serializeTableKey($key)
	{
		if (\preg_match(chr(1) . self::LUA_IDENTIFIER_PATTERN . chr(1),
			$key))
			return $key;
		elseif (\is_integer($key))
			return '[' . $key . ']';
		return '["' . \addslashes(TypeConversion::toString($key)) . '"]';
	}

	protected function serializeTable($table, $level = 0)
	{
		$first = true;
		$s = '';
		$pad = \str_repeat(' ', $level);
		if (Container::isIndexed($table))
		{
			foreach ($table as $value)
			{
				if (!$first)
					$s .= ",\n";
				$first = false;
				$s .= ' ';
				if (Container::isTraversable($value))
					$s .= $this->serializeTable($value, $level + 1);
				else
					$s .= $this->serializeLiteral($value);
			}
		}
		else
		{
			foreach ($table as $key => $value)
			{
				if (!$first)
					$s .= ",\n";
				$first = false;
				$s .= ' ';

				$s .= $this->serializeTableKey($key) . ' = ';
				if (Container::isTraversable($value))
					$s .= $this->serializeTable($value, $level + 1);
				else
					$s .= $this->serializeLiteral($value);
			}
		}

		$s .= "\n}";

		if ($level)
		{
			$s = \preg_replace('/^/m', $pad, $s);
		}

		return "{\n" . $s;
	}

	protected function serializeLiteral($value)
	{
		if (\is_null($value))
			return 'nil';
		if (\is_bool($value))
			return ($value) ? 'true' : 'false';
		if (\is_numeric($value))
			return $value;

		return '"' . \addslashes($value) . '"';
	}

	protected function buildMediaTypeList()
	{
		return [
			MediaTypeFactory::createFromString('text/x-lua')
		];
	}

	const INTEGER_PATTERN = '^[1-9][0-9]*$';

	const LUA_IDENTIFIER_PATTERN = '^[a-zA-Z_][a-zA-Z0-9_]*$';
}
