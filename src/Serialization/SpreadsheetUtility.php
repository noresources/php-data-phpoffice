<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * #package Data
 */
namespace NoreSources\Data\PhpOffice\Serialization;

class SpreadsheetUtility
{

	public static function getHighestRowAndColumn($sheet)
	{
		return $sheet->getHighestRowAndColumn();
	}
}
