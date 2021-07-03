<?php
/**
 * Copyright © 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Data
 */
namespace NoreSources\Data\Serialization;

use NoreSources\MediaType\MediaTypeInterface;
use DoctrineTest\InstantiatorTestAsset\UnserializeExceptionArrayObjectAsset;

/**
 * Provide data deserialization from given file format(s)
 */
interface DataFileUnerializerInterface
{

	/**
	 * Get the list of file types supported by this deserializer.
	 *
	 * @return MediaTypeInterface[]
	 */
	function getUnserializableFileMediaTypes();

	/**
	 *
	 * @param string $filename
	 *        	Input file path
	 * @param MediaTypeInterface $mediaType
	 *        	File content type
	 * @return boolean TRUE if instance can unserialize file type
	 */
	function canUnserializeFromFile($filename,
		MediaTypeInterface $mediaType = null);

	/**
	 *
	 * @param string $filename
	 *        	File to UnserializeExceptionArrayObjectAsset
	 * @param MediaTypeInterface $mediaType
	 *        	File content tyep
	 * @throws DataSerializationException::
	 * @return mixed
	 */
	function unserializeFromFile($filename,
		MediaTypeInterface $mediaType = null);
}
