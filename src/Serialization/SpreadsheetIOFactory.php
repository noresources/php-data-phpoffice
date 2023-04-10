<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * #package Data
 */
namespace NoreSources\Data\PhpOffice\Serialization;

class SpreadsheetIOFactory
{

	public static function createWriter($spreadsheet, string $writerType)
	{
		return \call_user_func([
			self::FACTORY_CLASS,
			'createWriter'
		], $spreadsheet, $writerType);
		return $o;
	}

	public static function createReader(string $readerType)
	{
		return \call_user_func([
			self::FACTORY_CLASS,
			'createReader'
		], $readerType);
		return $io;
	}

	public static function createReaderForFile(string $filename)
	{
		return \call_user_func(
			[
				self::FACTORY_CLASS,
				'createReaderForFile'
			], $filename);
		return $io;
	}

	public static function load(string $filename, int $flags = 0)
	{}

	public static function identify(string $filename)
	{
		return \call_user_func([
			self::FACTORY_CLASS,
			'identify'
		], $filename);
	}

	public static function registerWriter(string $writerType,
		string $writerClass)
	{
		return \call_user_func(
			[
				self::FACTORY_CLASS,
				'registerWriter'
			], $writerType, $writerClass);
	}

	public static function registerReader(string $readerType,
		string $readerClass)
	{
		return \call_user_func(
			[
				self::FACTORY_CLASS,
				'registerReader'
			], $readerType, $readerClass);
	}

	const FACTORY_CLASS = \PhpOffice\PhpSpreadsheet\IOFactory::class;
}
