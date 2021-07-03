<?php

/**
 * Copyright © 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * #package Data
 */
namespace NoreSources\Data\Serialization\Traits;

use NoreSources\MediaType\MediaTypeFileExtensionRegistry;
use NoreSources\MediaType\MediaTypeInterface;
use NoreSources\MediaType\MediaTypeFactory;

/**
 * DataFileUnserializer base on DataUnserializer implementation
 */
trait DataFileUnserializerTrait
{

	use DataFileMediaTypeNormalizerTrait;

	public function getUnserializableFileMediaTypes()
	{
		return $this->getUnserializableDataMediaTypes();
	}

	public function canUnserializeFromFile($filename,
		MediaTypeInterface $mediaType = null)
	{
		$mediaType = $this->normalizeFileMediaType($filename, $mediaType);
		if ($mediaType)
			return $this->canUnserializeData($mediaType);
		if (isset($this->extensions) && \is_array($this->extensions))
			return \in_array(\pathinfo($filename, PATHINFO_EXTENSION),
				$this->extensions);
		return false;
	}

	public function unserializeFromFile($filename,
		MediaTypeInterface $mediaType = null)
	{
		$serialized = \file_get_contents($filename);
		return $this->unserializeData($serialized);
	}
}
