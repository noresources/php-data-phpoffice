<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Data
 */
namespace NoreSources\Data\PhpOffice\Serialization;

use NoreSources\Container\Container;
use NoreSources\Data\Serialization\SerializableMediaTypeInterface;
use NoreSources\Data\Serialization\StreamSerializerInterface;
use NoreSources\Data\Utility\Traits\MediaTypeListTrait;
use NoreSources\Data\Utility\Traits\FileExtensionListTrait;
use NoreSources\MediaType\MediaTypeInterface;
use NoreSources\Data\Serialization\Traits\StreamSerializerMediaTypeTrait;
use NoreSources\Data\Utility\MediaTypeListInterface;
use NoreSources\Data\Utility\FileExtensionListInterface;
use NoreSources\MediaType\MediaTypeFactory;
use NoreSources\MediaType\MediaTypeException;
use NoreSources\Data\Serialization\DataSerializationException;
use NoreSources\Type\TypeConversion;
use NoreSources\Data\Analyzer;
use NoreSources\Data\Tableizer;
use NoreSources\Data\Serialization\FileSerializerInterface;
use NoreSources\Data\Serialization\Traits\StreamSerializerFileSerializerTrait;
use NoreSources\MediaType\MediaTypeMatcher;
use NoreSources\Data\Serialization\Traits\SerializableMediaTypeTrait;
use NoreSources\Data\Serialization\Traits\StreamSerializerBaseTrait;
use NoreSources\Data\Serialization\UnserializableMediaTypeInterface;
use NoreSources\Data\Serialization\Traits\UnserializableMediaTypeTrait;
use NoreSources\Data\Serialization\FileUnserializerInterface;
use NoreSources\Data\Serialization\SerializationException;
use NoreSources\Data\Serialization\StreamUnserializerInterface;
use NoreSources\Data\Serialization\Traits\StreamUnserializerBaseTrait;
use Psr\Container\ContainerExceptionInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Spreadsheet file (de)serializer
 *
 * Supported parameters
 * <ul>
 * <li>heading: Indicates if sheet(s) have row and/or column heading
 * <ul>
 * <li>auto: Auto-detect headings</li>
 * <li>none or <empty string>: No neading</li>
 * <li>row or rows: Row heading only</li>
 * <li>column or columns: Column heading only</li>
 * <li>both: Row and column heading</li>
 * < /ul>
 * </li>
 * <li>flatten: Flatten table if domension contains only one element.</li>
 * </ul>
 */
class SpreadsheetSerializer implements UnserializableMediaTypeInterface,
	SerializableMediaTypeInterface, FileUnserializerInterface,
	FileSerializerInterface, FileExtensionListInterface,
	StreamUnserializerInterface, StreamSerializerInterface
{
	use UnserializableMediaTypeTrait;
	use SerializableMediaTypeTrait;
	use StreamUnserializerBaseTrait;
	use StreamSerializerBaseTrait;

	/**
	 * Flatten table if dimension contains a single element
	 *
	 * @var string
	 */
	const PARAMETER_FLATTEN = 'flatten';

	/**
	 * Row and column heading mode
	 *
	 * @var string
	 */
	const PARAMETER_HEADING = 'heading';

	/**
	 * Auto-detect row and column headings
	 *
	 * @var string
	 */
	const PARAMETER_HEADING_AUTO = 'auto';

	/**
	 * No headings
	 *
	 * @var string
	 */
	const PARAMETER_HEADING_NONE = 'none';

	/**
	 * Row heading only
	 *
	 * @var string
	 */
	const PARAMETER_HEADING_ROW = 'row';

	/**
	 * Column heading only
	 *
	 * @var string
	 */
	const PARAMETER_HEADING_COLUMN = 'column';

	/**
	 * Both row and column headings
	 *
	 * @var string
	 */
	const PARAMETER_HEADING_BOTH = 'both';

	const SPREADSHEET_CLASS = \PhpOffice\PhpSpreadsheet\Spreadsheet::class;

	const TABLE_ROW_HEADER = 0x01;

	const TABLE_COLUMN_HEADER = 0x02;

	const COLUMN_OFFSET = 1;

	///////////////////////////////////////////////////////
	// UnserializableMediaTYpeInterface
	public function getUnserializableMediaRanges()
	{
		return self::getMediaRangesMatching(
			SpreadsheetIOEntry::READABLE);
	}

	///////////////////////////////////////////////////////
	// SerializableMediaTypeInterface

	/**
	 *
	 * @see \NoreSources\Data\Serialization\SerializableMediaTypeInterface::getSerializableMediaRanges()
	 */
	public function getSerializableMediaRanges()
	{
		return self::getMediaRangesMatching(
			SpreadsheetIOEntry::WRITABLE);
	}

	//////////////////////////////////
	// StreamUnserializerInterface
	public function unserializeFromStream($stream,
		MediaTypeInterface $mediaType = null)
	{
		$meta = \stream_get_meta_data($stream);
		$uri = Container::keyValue($meta, 'uri');
		if (!$uri)
			throw new SerializationException(
				'Unsupported stream (no URI)');
		return $this->unserializeFromFile($filename, $mediaType);
	}

	//////////////////////////////////
	// StreamSerializerInterface

	/**
	 *
	 * @see \NoreSources\Data\Serialization\StreamSerializerInterface::serializeToStream()
	 */
	public function serializeToStream($stream, $data,
		MediaTypeInterface $mediaType = null)
	{
		$writerType = $this->defaultWriterType;
		if ($mediaType)
		{
			$entry = $this->getIOEntryForMediaType(
				SpreadsheetIOEntry::WRITABLE, $mediaType);
			if (!$entry)
				throw new SerializationException(
					'No writer available for ' . \strval($mediaType));
			$writerType = $entry->type;
		}
		else // Last chance
		{
			$mediaTypeFactory = MediaTypeFactory::getInstance();
			try
			{
				$mediaType = $mediaTypeFactory->createFromMedia($stream);
			}
			catch (MediaTypeException $e)
			{}
			if ($mediaType)
			{
				$e = $this->getIOEntryForMediaType(
					SpreadsheetIOEntry::WRITABLE, $mediaType);
				if ($e)
					$writerType = $e->type;
			}
		}

		$spreadsheet = $this->createSpreadsheet($data);
		$writer = SpreadsheetIOFactory::createWriter($spreadsheet,
			$writerType);
		$writer->save($stream);
		$spreadsheet->disconnectWorksheets();
		$spreadsheet = null;
	}

	//////////////////////////////////////////////////////
	// FileUnserializerInterface,
	public function isUnserializableFromFile($filename,
		MediaTypeInterface $mediaType = null)
	{
		$extension = null;
		$testExtension = \is_string($filename) &&
			($extension = @\pathinfo($filename, PATHINFO_EXTENSION));
		if ($testExtension && !$mediaType)
		{
			$extensions = self::getFileExtensionsMatching(
				SpreadsheetIOEntry::READABLE);
			return \in_array($extension, $extensions);
		}

		if (!$mediaType)
		{
			try
			{
				$mediaType = MediaTypeFactory::getInstance()->createFromMedia(
					$filename, MediaTypeFactory::FROM_EXTENSION);
			}
			catch (MediaTypeException $e)
			{}
		}

		if ($mediaType)
			return $this->isMediaTypeSerializable($mediaType);
		if ($testExtension)
		{
			$extensions = self::getFileExtensionsMatching(
				SpreadsheetIOEntry::READABLE);
			return \in_array($extension, $extensions);
		}
	}

	public function unserializeFromFile($filename,
		MediaTypeInterface $mediaType = null)
	{
		if (!$mediaType &&
			($entry = self::getIOEntryForExtension(
				SpreadsheetIOEntry::READABLE,
				\pathinfo($filename, PATHINFO_EXTENSION))))
		{
			$mediaType = $entry->mediaType;
		}

		$reader = null;
		$data = null;

		if ($mediaType &&
			($e = $this->getIOEntryForMediaType(
				SpreadsheetIOEntry::READABLE, $mediaType)) &&
			($readerType = $e->type))
		{
			$reader = SpreadsheetIOFactory::createReader($readerType);
		}

		if (!$reader)
			$reader = SpreadsheetIOFactory::createReaderForFile(
				$filename);

		$heading = Container::keyValue($mediaType->getParameters(),
			'heading', self::PARAMETER_HEADING_AUTO);
		$tableFlags = self::headingModeToFlags($heading);

		$reader->setLoadSheetsOnly(true);
		$spreadsheet = $reader->load($filename);
		$spreadsheet->setHasMacros(false);
		$data = $this->createTable($spreadsheet, $tableFlags);
		$spreadsheet->disconnectWorksheets();
		$spreadsheet = null;

		if ($mediaType->getParameters()->has(self::PARAMETER_FLATTEN))
		{
			if (Container::count($data) == 1)
				$data = Container::firstValue($data);
		}

		return $data;
	}

	//////////////////////////////////////////////////////
	// FileSerializerInterface

	/**
	 *
	 * @see \NoreSources\Data\Serialization\FileSerializerInterface::isSerializableToFile()
	 */
	public function isSerializableToFile($filename, $data,
		MediaTypeInterface $mediaType = null)
	{
		$extension = null;
		$testExtension = \is_string($filename) &&
			($extension = @\pathinfo($filename, PATHINFO_EXTENSION));
		if ($testExtension && !$mediaType)
		{
			$extensions = self::getFileExtensionsMatching(
				SpreadsheetIOEntry::WRITABLE);
			return \in_array(pathinfo($filename, PATHINFO_EXTENSION),
				$extensions);
		}

		if (!$mediaType)
		{
			try
			{
				$mediaType = MediaTypeFactory::getInstance()->createFromMedia(
					$filename, MediaTypeFactory::FROM_EXTENSION);
			}
			catch (MediaTypeException $e)
			{}
		}

		if ($mediaType)
			return $this->isMediaTypeSerializable($mediaType);

		if ($testExtension)
		{
			$extensions = self::getFileExtensionsMatching(
				SpreadsheetIOEntry::WRITABLE);
			return \in_array(pathinfo($filename, PATHINFO_EXTENSION),
				$extensions);
		}

		return true;
	}

	/**
	 *
	 * @see \NoreSources\Data\Serialization\FileSerializerInterface::serializeToFile()
	 */
	public function serializeToFile($filename, $data,
		MediaTypeInterface $mediaType = null)
	{
		if (!$mediaType &&
			($entry = self::getIOEntryForExtension(
				SpreadsheetIOEntry::WRITABLE,
				\pathinfo($filename, PATHINFO_EXTENSION))))
		{
			$mediaType = $entry->mediaType;
		}

		$stream = @\fopen($filename, 'w');
		if ($stream === false)
		{
			$error = \error_get_last();
			throw new SerializationException($error['message']);
		}

		try
		{
			$this->serializeToStream($stream, $data, $mediaType);
		}
		catch (\Exception $e)
		{
			@\fclose($stream);
			throw $e;
		}
		@\fclose($stream);
	}

	////////////////////////////////////////////////////////////
	// FileExtensionListInterface

	/**
	 *
	 * @see \NoreSources\Data\Utility\FileExtensionListInterface::getFileExtensions()
	 */
	public function getFileExtensions()
	{
		$entries = self::getSpreadsheetIOEntries();
		return \array_unique(
			\array_map(function ($e) {
				return $e->extension;
			}, $entries));
		;
	}

	public function matchFileExtension($extension)
	{
		return \in_array($extension, $this->getFileExtensions());
	}

	//////////////////////////////////////////////////////////////////
	public static function getMediaRangesMatching($flags)
	{
		$entries = self::getSpreadsheetIOEntries();
		return \array_map(function ($e) {
			return $e->mediaType;
		},
			\array_filter($entries,
				function ($e) use ($flags) {
					if (!$e->mediaType)
						return false;
					return (($e->flags & $flags) & $flags);
				}));
	}

	public static function getFileExtensionsMatching($flags)
	{
		$entries = self::getSpreadsheetIOEntries();
		return \array_map(function ($e) {
			return $e->extension;
		},
			\array_filter($entries,
				function ($e) use ($flags) {
					return (($e->flags & $flags) & $flags);
				}));
	}

	/**
	 *
	 * @param integer $ioTypeFlag
	 *        	IO type flags
	 * @param MediaTypeInterface $mediaType
	 *        	Media type
	 * @return \preadsheetIOEntry|boolean
	 */
	public static function getIOEntryForMediaType($ioTypeFlag,
		MediaTypeInterface $mediaType)
	{
		$entries = self::getSpreadsheetIOEntries();
		foreach ($entries as $e)
		{
			if (!$e->mediaType)
				continue;
			if (($e->flags & $ioTypeFlag) != $ioTypeFlag)
				continue;
			if ($e->mediaType->match($mediaType))
				return $e;
		}
		return false;
	}

	public static function getIOEntryForExtension($ioTypeFlag,
		$extension)
	{
		$entries = self::getSpreadsheetIOEntries();
		foreach ($entries as $e)
		{
			if (($e->flags & $ioTypeFlag) != $ioTypeFlag)
				continue;
			if ($e->extension == $extension)
				return $e;
		}
		return false;
	}

	public static function getSpreadsheetIOEntries()
	{
		if (isset(self::$ioEntries))
			return self::$ioEntries;

		$mediaTypeFactory = MediaTypeFactory::getInstance();

		$r = SpreadsheetIOEntry::READABLE;
		$w = SpreadsheetIOEntry::WRITABLE;
		$rw = $r | $w;
		$pdfMediaType = $mediaTypeFactory->createFromString(
			'application/pdf');

		self::$ioEntries = [
			new SpreadsheetIOEntry('Xlsx', $rw,
				$mediaTypeFactory->createFromString(
					"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")),
			new SpreadsheetIOEntry('Xls', $rw,
				$mediaTypeFactory->createFromString(
					"application/vnd.ms-excel")),
			new SpreadsheetIOEntry('Ods', $rw,
				$mediaTypeFactory->createFromString(
					"application/vnd.oasis.opendocument.spreadsheet")),
			new SpreadsheetIOEntry('Csv', $rw,
				$mediaTypeFactory->createFromString('text/csv')),
			new SpreadsheetIOEntry('Html', $rw,
				$mediaTypeFactory->createFromString('text/html')),
			// Readers
			new SpreadsheetIOEntry('Xml', $r,
				$mediaTypeFactory->createFromString('application/xml')),
			new SpreadsheetIOEntry('Slk', $r),
			new SpreadsheetIOEntry('Gnumeric', $r,
				$mediaTypeFactory->createFromString(
					"application/x-gnumeric")),
			// Writers
			new SpreadsheetIOEntry('Tcpdf', $w, $pdfMediaType, 'pdf'),
			new SpreadsheetIOEntry('Dompdf', $w, $pdfMediaType, 'pdf'),
			new SpreadsheetIOEntry('Mpdf', $w, $pdfMediaType, 'pdf')
		];

		return self::$ioEntries;
	}

	public function setStringifier($callable)
	{
		$this->stringifier = $callable;
	}

	public function stringify($data)
	{
		if (\is_callable($this->stringifier))
			return \call_user_func($this->stringifier, $data);
		if (\is_object($data) || \is_array($data))
			return TypeConversion::toString($data);
		return $data;
	}

	protected function createTable($spreadsheet, $tableFlags)
	{
		$sheetCount = $spreadsheet->getSheetCount();
		if ($sheetCount == 0)
			return [
				[]
			];
		if ($sheetCount == 1)
		{
			$sheet = $spreadsheet->getActiveSheet();
			$title = $sheet->getTitle();
			$hasTitle = \is_string($title) && \strval($title) &&
				!\is_numeric($title);
			$table = $this->createTableFromWorksheet($sheet, $tableFlags);
			if ($hasTitle)
				return [
					$title => $table
				];
			return [
				$table
			];
		}

		$data = [];
		$iterator = $spreadsheet->getWorksheetIterator();
		foreach ($iterator as $sheet)
		{
			$title = $sheet->getTitle();
			$hasTitle = \is_string($title) && \strval($title) &&
				!\is_numeric($title);
			$table = $this->createTableFromWorksheet($sheet, $tableFlags);
			if ($hasTitle)
				$data[$title] = $table;
			else
				$data[] = $sheet;
		}

		return $data;
	}

	protected function createTableFromWorksheet($sheet, $tableFlags)
	{
		$collection = $sheet->getCellCollection();
		$max = $collection->getHighestRowAndColumn($sheet);
		$max['column'] = Coordinate::columnIndexFromString(
			$max['column']);

		if ($tableFlags === null)
		{
			$tableFlags = 0;
			$pivot = $sheet->getCellByColumnAndRow(self::COLUMN_OFFSET,
				1)->getValue();
			if ($pivot === null)
			{
				$tableFlags = self::TABLE_ROW_HEADER |
					self::TABLE_COLUMN_HEADER;
			}
		}

		$rowKeys = [];
		$columnKeys = [];
		$firstRow = 1;
		$firstColumn = self::COLUMN_OFFSET;
		if ($tableFlags & self::TABLE_ROW_HEADER)
			$firstColumn++;
		if ($tableFlags & self::TABLE_COLUMN_HEADER)
			$firstRow++;

		if ($tableFlags & self::TABLE_ROW_HEADER)
		{
			for ($r = $firstRow; $r <= $max['row']; $r++)
			{
				$cell = $sheet->getCellByColumnAndRow(
					self::COLUMN_OFFSET, $r);
				$rowKeys[$r] = $this->stringify($cell->getValue());
			}
		}
		else
		{
			for ($r = $firstRow; $r <= $max['row']; $r++)
				$rowKeys[$r] = \count($rowKeys);
		}

		if ($tableFlags & self::TABLE_COLUMN_HEADER)
		{
			for ($c = $firstColumn; $c <= $max['column']; $c++)
			{
				$cell = $sheet->getCellByColumnAndRow($c, 1);
				$columnKeys[$c] = $this->stringify($cell->getValue());
			}
		}
		else
		{
			for ($c = $firstColumn; $c <= $max['column']; $c++)
				$columnKeys[$c] = \count($columnKeys);
		}

		$data = [];
		for ($r = $firstRow; $r <= $max['row']; $r++)
		{
			$row = [];
			for ($c = $firstColumn; $c <= $max['column']; $c++)
			{
				$cell = $sheet->getCellByColumnAndRow($c, $r);
				$value = $cell->getValue();
				if ($value === null)
					continue;
				if (\is_object($value))
					$value = $this->stringify($value);

				$row[$columnKeys[$c]] = $value;
			}

			$data[$rowKeys[$r]] = $row;
		}

		return $data;
	}

	protected function createSpreadsheet($data)
	{
		$cls = new \ReflectionClass(static::SPREADSHEET_CLASS);
		$spreadsheet = null;
		if (\is_object($data) && \is_a($data, $cls->getName(), true))
			$spreadsheet = $data;
		else
			$spreadsheet = $cls->newInstance();

		$tableizer = new Tableizer();
		$tableizer->setCellNormalizer([
			$this,
			'stringify'
		]);

		$analyzer = new Analyzer();
		$depth = $analyzer->getDataMinDepth($data);

		if ($depth > 2)
		{
			$offset = 0;
			foreach ($data as $title => $value)
			{
				$table = $tableizer($value);
				$sheet = null;
				if ($offset == 0)
					$sheet = $spreadsheet->getActiveSheet();
				else
					$sheet = $spreadsheet->createSheet();
				$sheet->setTitle($title);
				$this->populateWorksheet($sheet, $table);

				$offset++;
			}

			return $spreadsheet;
		}

		$table = $tableizer($data);
		$sheet = $spreadsheet->getActiveSheet();
		$this->populateWorksheet($sheet, $table);
		return $spreadsheet;
	}

	/**
	 *
	 * @param Worksheet $sheet
	 *        	Spreadsheet work sheet
	 * @param array $table
	 *        	2D Data array
	 */
	protected function populateWorksheet($sheet, $table)
	{
		$r = 1;
		foreach ($table as $row)
		{
			$c = self::COLUMN_OFFSET;
			foreach ($row as $column)
			{
				if ($column !== null)
					$sheet->setCellValueByColumnAndRow($c, $r, $column);
				$c++;
			}
			$r++;
		}
	}

	/**
	 *
	 * @param mixed $heading
	 *        	Heading mode
	 * @throws \InvalidArgumentException
	 * @return integer Heading flags
	 */
	protected function headingModeToFlags($heading)
	{
		$mode = $heading;
		if (\is_integer($heading))
			return $heading;
		if (\is_null($heading))
			return null;
		if ($heading === true)
			return self::TABLE_COLUMN_HEADER | self::TABLE_ROW_HEADER;
		if ($heading === false)
			return 0;
		$mode = \strtolower($mode);
		switch ($mode)
		{
			case self::PARAMETER_HEADING_AUTO:
			case '':
				return null;
			case self::PARAMETER_HEADING_NONE:
				return 0;
			case self::PARAMETER_HEADING_ROW:
			case 'rows':
				return self::TABLE_ROW_HEADER;
			case self::PARAMETER_HEADING_COLUMN:
			case 'columns':
				return self::TABLE_COLUMN_HEADER;
			case self::PARAMETER_HEADING_BOTH:
				return self::TABLE_ROW_HEADER | self::TABLE_COLUMN_HEADER;
		}
		throw new \InvalidArgumentException(
			'Unexpected headeing mode "' . $mode . '"');
	}

	/**
	 *
	 * @var SpreadsheetIOEntry[]
	 */
	private static $ioEntries;

	private $defaultWriterType = 'Ods';

	private $defaultReaderType = 'Ods';

	/**
	 *
	 * @var callable
	 */
	private $stringifier;
}
