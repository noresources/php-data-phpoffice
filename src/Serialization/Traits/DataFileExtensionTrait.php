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

	protected function matchExtension($filename)
	{
		if (!\is_array($this->extensions))
			return false;
		if (!\is_string($filename))
			return false;
		return \in_array(\pathinfo($filename, PATHINFO_EXTENSION),
			$this->extensions);
	}

	private $extensions;
}
