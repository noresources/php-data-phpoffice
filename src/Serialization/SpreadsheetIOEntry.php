<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Data
 */
namespace NoreSources\Data\PhpOffice\Serialization;

use NoreSources\MediaType\MediaTypeInterface;
use NoreSources\Bitset;

class SpreadsheetIOEntry
{

	const READABLE = Bitset::BIT_01;

	const WRITABLE = Bitset::BIT_02;

	/**
	 * Reader / Writer type
	 *
	 * @var string
	 */
	public $type;

	/**
	 * File format extension
	 *
	 * @var string
	 */
	public $extension;

	/**
	 * File format media type
	 *
	 * @var MediaTypeInterface|NULL
	 */
	public $mediaType;

	/**
	 * Property flags
	 *
	 * @var integer
	 */
	public $flags = 0;

	public function __construct($type, $flags,
		MediaTypeInterface $mediaType = null, $extension = null)
	{
		$this->type = $type;
		$this->mediaType = $mediaType;
		$this->flags = $flags;
		$this->extension = ($extension) ? $extension : \strtolower(
			$type);
	}
}
