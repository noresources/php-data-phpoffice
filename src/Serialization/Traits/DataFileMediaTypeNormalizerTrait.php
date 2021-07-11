<?php

/**
 * Copyright © 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * #package Data
 */
namespace NoreSources\Data\Serialization\Traits;

use NoreSources\MediaType\MediaTypeInterface;
use NoreSources\MediaType\MediaTypeFactory;

trait DataFileMediaTypeNormalizerTrait
{

	/**
	 *
	 * @param string $filename
	 * @param MediaTypeInterface $mediaType
	 * @return MediaTypeInterface
	 */
	private function normalizeFileMediaType($filename,
		MediaTypeInterface $mediaType = null)
	{
		if ($mediaType instanceof MediaTypeInterface)
			return $mediaType;
		try
		{
			if (\is_string($mediaType))
				$mediaType = MediaTypeFactory::fromString($mediaType);
			elseif ($filename)
				$mediaType = MediaTypeFactory::fromMedia($filename,
					$this->getMediaTypeFactoryFlags());
		}
		catch (\Exception $e)
		{
			return null;
		}

		return ($mediaType && \strval($mediaType) == 'text/plain') ? null : $mediaType;
	}

	protected function getMediaTypeFactoryFlags()
	{
		return MediaTypeFactory::FROM_ALL;
	}
}
