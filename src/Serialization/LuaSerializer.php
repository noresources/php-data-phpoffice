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
		if (Container::isTraversable($data))
		{
			return $this->serializeTable($data);
		}

		return $this->serializeLiteral($data);
	}

	public function serializeToFile($filename, $data,
		MediaTypeInterface $mediaType = null)
	{
		$serialized = 'return ' . $this->serializeData($data, $mediaType);
		\file_put_contents($filename, $serialized);
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
