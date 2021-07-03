<?php

/**
 * Copyright © 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * #package Data
 */
namespace NoreSources\Data\Serialization\Traits;

use NoreSources\MediaType\MediaTypeInterface;
use NoreSources\MediaType\MediaTypeFileExtensionRegistry;
use NoreSources\Type\TypeDescription;

trait DataFileSerializerTrait
{

	public function getSerializableFileMediaTypes()
	{
		return $this->getSerializableDataMediaTypes();
	}

	public function canSerializeToFile($filename, $data,
		MediaTypeInterface $mediaType = null)
	{
		if (!$mediaType)
			$mediaType = MediaTypeFileExtensionRegistry::getInstance()->mediaTypeFromExtension(
				\pathinfo($filename, PATHINFO_EXTENSION));
		if ($mediaType)
			return $this->canSerializeData($data, $mediaType);
		if (isset($this->extensions) && \is_array($this->extensions))
			return \in_array(\pathinfo($filename, PATHINFO_EXTENSION),
				$this->extensions);
		return false;
	}

	public function serializeToFile($filename, $data,
		MediaTypeInterface $mediaType = null)
	{
		$serialized = $this->serializeData($data, $mediaType);
		\file_put_contents($filename, $serialized);
	}
}
