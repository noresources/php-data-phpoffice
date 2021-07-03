<?php

/**
 * Copyright © 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * #package Data
 */
namespace NoreSources\Data\Serialization\Traits;

trait DataFileExtensionTrait
{

	protected function setFileExtensions($array = array())
	{
		$this->extensions = $array;
	}

	private $extensions;
}
