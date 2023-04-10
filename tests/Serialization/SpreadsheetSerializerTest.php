<?php

/**
 * Copyright © 2023 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * #package Data
 */
namespace NoreSources\Data\PhpOffice\TestCase\Serialization;

use NoreSources\Data\PhpOffice\Serialization\SpreadsheetSerializer;
use NoreSources\Container\ContainerPropertyInterface;
use NoreSources\Container\Container;
use NoreSources\MediaType\MediaTypeFactory;
use NoreSources\Data\Test\SerializerTestCaseBase;
use NoreSources\Data\Test\SerializerAssertionTrait;
use NoreSources\Test\DerivedFileTestTrait;
use NoreSources\Data\Serialization\FileSerializerInterface;
use NoreSources\Data\Serialization\Traits\StreamSerializerDataSerializerTrait;
use NoreSources\Data\Serialization\StreamSerializerInterface;
use NoreSources\MediaType\MediaTypeInterface;
use NoreSources\Data\Utility\FileExtensionListInterface;
use NoreSources\MediaType\MediaTypeMatcher;
use NoreSources\Data\Utility\MediaTypeListInterface;
use NoreSources\Data\PhpOffice\Serialization\SpreadsheetIOEntry;
use Composer\InstalledVersions;
use NoreSources\SemanticVersion;

class SpreadsheetSerializerTestClass implements
	ContainerPropertyInterface
{

	public $firstName = 'John';

	public $lastName = 'Doe';

	private $salary = 12345.678;

	public function getContainerProperties()
	{
		return Container::properties($this, true) |
			Container::TRAVERSABLE;
	}
}

class SpreadsheetSerializerTest extends \PHPUnit\Framework\TestCase
{

	use SerializerAssertionTrait;

	const CLASS_NAME = SpreadsheetSerializer::class;

	public function setUp(): void
	{
		$this->initializeSerializerAssertions(self::CLASS_NAME,
			__DIR__ . '/..');
	}

	public function tearDown(): void
	{
		$this->cleanupDerivedFileTest();
	}

	public function testSerialization()
	{
		$version = InstalledVersions::getPrettyVersion(
			'phpoffice/phpspreadsheet');
		$version = new SemanticVersion($version);
		$version = $version->major . '_' . $version->minor;
		$serializer = new SpreadsheetSerializer();
		$tests = [
			'CSV' => [
				'extension' => 'csv',
				'mediaType' => 'text/csv'
			],/*
			'OpenDocument spreadsheet' => [
				'mediaType' => "application/vnd.oasis.opendocument.spreadsheet",
				'extension' => 'ods'
			]*/
			'html' => [
				'mediaType' => 'text/html',
				'extension' => 'html',
				'suffix' => $version
			]
		];
		foreach ($tests as $description => $test)
		{
			$extension = $test['extension'];
			$mediaType = MediaTypeFactory::getInstance()->createFromString(
				$test['mediaType']);

			$text = $description . ' with extension only';
			$suffix = Container::keyValue($test, 'suffix');
			$this->runSerializerForExtension(__METHOD__, $suffix, $text,
				$extension);
			$text = $description . ' with media type only';
			$this->runSerializerForExtension(__METHOD__, $suffix, $text,
				null, $mediaType);
			$text = $description . ' using both extension and media type';
			$this->runSerializerForExtension(__METHOD__, $suffix, $text,
				$extension, $mediaType);
		}
	}

	public function runSerializerForExtension($method, $suffix, $text,
		$extension = null, MediaTypeInterface $mediaType = null)
	{
		$serializer = new SpreadsheetSerializer();

		$tests = [
			'literal' => 'Hello world',
			'array' => [
				'One',
				'Two',
				'Three'
			],
			'object' => [
				'key' => 'value',
				'foo' => 'bar'
			],
			'array of object' => [
				[
					'name' => 'Bob',
					'Gender' => 'M',
					'Age' => 42
				],
				[
					'name' => 'Alice',
					'Gender' => 'F',
					'Mood' => 'happy'
				]
			]
		];

		foreach ($tests as $label => $data)
		{
			$suffix = \str_replace(' ', '_', $label) .
				($suffix ? '_' . $suffix : '');
			$label = $label . ' ' . $text;

			if ($extension)
			{
				if ($serializer instanceof FileExtensionListInterface)
				{
					$this->assertContains($extension,
						$serializer->getFileExtensions(),
						$label . ' supports extension ' . $extension);
				}
				$filename = $this->getDerivedFilename($method, $suffix,
					$extension);

				$this->assertCreateFileDirectoryPath($filename, $label);
				$this->assertTrue(
					$serializer->isSerializableToFile($filename, $data,
						$mediaType),
					'Can serialize ' . $label . ' to file');

				$serializer->serializeToFile($filename, $data,
					$mediaType);

				$this->assertDerivedFileEqualsReferenceFile($method,
					$suffix, $extension,
					$label . ' result of serialization to file');
			}
			else
			{
				$e = $serializer->getIOEntryForMediaType(
					SpreadsheetIOEntry::WRITABLE, $mediaType);
				$extension = $e->extension;
			}

			if ($mediaType)
			{
				if ($serializer instanceof MediaTypeListInterface)
				{
					$matcher = new MediaTypeMatcher($mediaType);
					$ranges = $serializer->getSerializableMediaTypes();
					$this->assertTrue($matcher->match($ranges),
						$label . ' supporgs media type ' .
						\strval($mediaType));
				}

				if ($serializer instanceof StreamSerializerInterface)
				{
					$stream = \fopen('php://memory', 'rw');
					$this->assertNotFalse($stream,
						'Open ' . $label . ' stream is serializable');

					$this->assertTrue(
						$serializer->isSerializableToStream($data,
							$mediaType),
						$label . ' is serializable to stream');

					$serializer->serializeToStream($stream, $data,
						$mediaType);
					\fflush($stream);

					$this->assertStreamEqualsReferenceFile($stream,
						$method, $suffix, $extension);
					\fclose($stream);
				}
			}
		}
	}

	public function test3DTable()
	{
		$data = [
			"start-app" => [
				"welcome" => [
					"fra" => "Bienvenue",
					"eng" => "Welcome",
					"nld" => "welkom"
				],
				"start" => [
					"fra" => "Appuyez sur le bouton",
					"eng" => "Push to start"
				]
			],
			"final-app" => [
				"welcome" => [
					"deu" => "Willkommen",
					"eng" => "You! Again ?!",
					"fra" => "Encore vous ?!"
				],
				"end" => [
					"fra" => "C'est la fin",
					"eng" => "The end"
				]
			]
		];

		$method = __METHOD__;
		$suffix = null;
		$extension = 'ods';
		/**
		 *
		 * @var SpreadsheetSerializer $serializer
		 */
		$serializer = $this->createSerializer();
		$filename = $this->getDerivedFilename($method, $suffix,
			$extension);

		$this->assertTrue(
			$serializer->isSerializableToFile($filename, $data),
			'Cna serialize to ODS');

		$serializer->serializeToFile($filename, $data);
		$unserialized = $serializer->unserializeFromFile($filename);
		$this->assertEquals($data, $unserialized, 'Unserialized back');
	}
}
