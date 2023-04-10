<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * #package Data
 */
namespace NoreSources\Data\PhpOffice\Serialization;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class SpreadsheetUtility
{

	public static function columnIndexFromString($s)
	{
		return Coordinate::columnIndexFromString($s);
	}

	public static function getHighestRowAndColumn($sheet)
	{
		$collection = $sheet->getCellCollection();
		return $collection->getHighestRowAndColumn();
	}
}


