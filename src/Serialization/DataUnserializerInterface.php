<?php
/**
 * Copyright © 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Data
 */
namespace NoreSources\Data\Serialization;

use NoreSources\MediaType\MediaTypeInterface;

/**
 * Provide data deserialization for one or more content type
 */
interface DataUnserializerInterface
{

	/**
	 *
	 * @return MediaTypeInterface[]
	 */
	function getUnserializableDataMediaTypes();

	/**
	 *
	 * @param MediaTypeInterface $mediaType
	 * @return TRUE if instance support de-serialization of $mediaType content
	 */
	function canUnserializeData(MediaTypeInterface $mediaType);

	/**
	 *
	 * @param string $data
	 * @param MediaTypeInterface $mediaType
	 *        	Serialized content type
	 * @return mixed
	 */
	function unserializeData($data, MediaTypeInterface $mediaType = null);
}
