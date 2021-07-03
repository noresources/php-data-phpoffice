<?php

/**
 * Copyright © 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * #package Data
 */
namespace NoreSources\Data\Serialization\Traits;

use NoreSources\MediaType\MediaTypeInterface;

trait MediaTypeListTrait
{

	protected function buildMediaTypeList()
	{
		throw new \LogicException('Not implementaed');
	}

	protected function getMediaTypes()
	{
		if (!isset($this->mediaTypes))
		{
			$list = $this->mediaTypes = $this->buildMediaTypeList();
			$this->mediaTypes = [];
			foreach ($list as $type)
				$this->mediaTypes[\strval($type)] = $type;
		}
		return $this->mediaTypes;
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

		return false;
	}

	/**
	 *
	 * @var array<string, MediaTypeInterface>
	 */
	private $mediaTypes;
}
