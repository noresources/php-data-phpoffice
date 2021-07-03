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
			$mediaType = MediaTypeFactory::fromMedia($filename);
		}
		catch (\Exception $e)
		{
			return null;
		}

		return (\strval($mediaType) == 'text/plain') ? null : $mediaType;
	}
}
