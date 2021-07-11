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
use NoreSources\Data\Serialization\DataUnserializerInterface;

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
		if (\method_exists($this, 'normalizeFileMediaType'))
			$mediaType = \call_user_func(
				[
					$this,
					'normalizeFileMediaType'
				], $filename, $mediaType);
		if ($mediaType && $this instanceof DataUnserializerInterface)
			return $this->canUnserializeData($mediaType);
		if (\method_exists($this, 'matchExtension'))
			return \call_user_func([
				$this,
				'matchExtension'
			], $filename);
		return false;
	}

	public function unserializeFromFile($filename,
		MediaTypeInterface $mediaType = null)
	{
		$serialized = \file_get_contents($filename);
		return $this->unserializeData($serialized);
	}
}
