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
 * Provide data serialization to a given file format
 */
interface DataFileSerializerInterface
{

	/**
	 * Get the list of file type supported by this serializer.
	 *
	 * @return MediaTypeInterface[]
	 */
	function getSerializableFileMediaTypes();

	/**
	 *
	 * @param string $filename
	 *        	Output file path
	 *        	* @param mixed $data
	 *        	Data to srialize
	 * @param MediaTypeInterface $mediaType
	 *        	Target content type
	 *
	 */
	function canSerializeToFile($filename, $data,
		MediaTypeInterface $mediaType = null);

	/**
	 *
	 * @param string $filename
	 *        	Target file path
	 * @param MediaTypeInterface $mediaType
	 *        	File content type
	 */
	function serializeToFile($filename, $data,
		MediaTypeInterface $mediaType = null);
}
