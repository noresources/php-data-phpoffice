<?php
/**
 * Copyright © 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * #package Data
 */
namespace NoreSources\Data\Test;

use NoreSources\Container\Container;
use NoreSources\Text\StructuredText;
use NoreSources\MediaType\MediaTypeInterface;
use NoreSources\MediaType\MediaTypeFactory;
use NoreSources\Data\Serialization\SerializationManager;
use NoreSources\Data\Serialization\DataSerializationManager;
use NoreSources\Data\Serialization\IniSerializer;
use NoreSources\Data\Serialization\UrlEncodedSerializer;
use NoreSources\Data\Serialization\CsvSerializer;
use NoreSources\Type\TypeDescription;

final class SerializerTest extends \PHPUnit\Framework\TestCase
{

	public function testIni()
	{
		$directory = __DIR__ . '/../data';
		$ini = new IniSerializer();

		foreach ([
			'a'
		] as $name)
		{
			$filename = $directory . '/' . $name . '.ini';

			$this->assertTrue($ini->canUnserializeFromFile($filename),
				'Can unserialize ' .
				\pathinfo($filename, PATHINFO_FILENAME));

			$data = $ini->unserializeFromFile($filename);
		}
	}

	public function testRegularCsv()
	{
		if (Container::keyValue($_ENV, 'TEST_CSV', 'no') != 'yes')
		{
			$this->assertTrue(true);
			return;
		}

		$input = [
			[
				'ID',
				'name',
				'followers',
				'haters'
			],
			[
				1,
				'Bob',
				42,
				314159
			],
			[
				2,
				'Alice',
				123456,
				1
			],
			[
				666,
				'John Carmack',
				918700,
				0
			]
		];

		$serializer = new CsvSerializer();

		$valid = $serializer->canSerializeData($input);
		if (!$valid)
		{
			$this->assertFalse($valid);
			return;
		}

		$serialized = $serializer->serializeData($input);
		$this->assertTrue(\is_string($serialized),
			'Serialization is string');
		$deserialized = $serializer->unserializeData($serialized);

		$this->assertEquals($deserialized, $input,
			'Serialization/Deserialization cycle');

		$filename = __DIR__ . '/../data/table.csv';
		$deserializedFile = $serializer->unserializeFromFile($filename);

		$this->assertEquals($input, $deserializedFile,
			'Deserialize file');
	}

	public function testCsvTransform()
	{
		if (Container::keyValue($_ENV, 'TEST_CSV', 'no') != 'yes')
		{
			$this->assertTrue(true);
			return;
		}

		$tests = [
			'literal' => [
				'input' => 123,
				'output' => [
					[
						123
					]
				]
			],
			'object' => [
				'input' => [
					'id' => 5,
					'name' => 'Bob',
					'age' => 42
				],
				'output' => [
					[
						'id',
						5
					],
					[
						'name',
						'Bob'
					],
					[
						'age',
						42
					]
				]
			],
			'collection' => [
				'input' => [
					[
						'id' => 5,
						'name' => 'Bob',
						'age' => 42
					],
					[
						'name' => 'Alice',
						'sex' => 'F'
					],
					[
						'foo' => 'bar'
					]
				],
				'output' => [
					[
						'id',
						'name',
						'age',
						'sex',
						'foo'
					],
					[
						5,
						'Bob',
						42,
						null,
						null
					],
					[
						null,
						'Alice',
						null,
						'F',
						null
					],
					[
						null,
						null,
						null,
						null,
						'bar'
					]
				]
			]
		];

		$serializer = new CsvSerializer();

		foreach ($tests as $label => $test)
		{
			$input = $test['input'];
			$output = $test['output'];

			$valid = $serializer->canSerializeData($input);
			if (!$valid)
			{
				$this->assertFalse($valid);
				continue;
			}

			$serialized = $serializer->serializeData($input);
			$deserialized = $serializer->unserializeData($serialized);

			$this->assertEquals($output, $deserialized,
				$label . ' serialization/deserialization');
		}
	}

	public function testUrlEncoded()
	{
		$serializer = new UrlEncodedSerializer();

		foreach ([
			'Test' => 'text',
			'A text with space' => 'A text with space',
			'Key-values' => [
				'key' => 'value',
				'Complex' => 'A more "tricky" string'
			]
		] as $label => $value)
		{
			$serialized = $serializer->serializeData($value);
			$unserialized = $serializer->unserializeData($serialized);

			$this->assertEquals($value, $unserialized,
				'Serialization/Deserialization cycle');
		}
	}

	public function testManager()
	{
		$manager = new DataSerializationManager();

		foreach ([
			'getUnserializableDataMediaTypes',
			'getSerializableDataMediaTypes',
			'getUnserializableFileMediaTypes',
			'getSerializableFileMediaTypes'
		] as $method)
		{
			$result = \call_user_func([
				$manager,
				$method
			]);
			$this->assertEquals('array',
				TypeDescription::getName($result));
		}

		foreach ([
			'a' => 'A file'
		] as $key => $label)
		{
			foreach ([
				'json',
				'yaml'
			] as $extension)
			{
				if (!\extension_loaded($extension))
					continue;
				$filename = __DIR__ . '/../data/' . $key . '.' .
					$extension;
				$derivedFilename = __DIR__ . '/../derived/' . $key . '.' .
					$extension;
				$this->assertFileExists($filename,
					$extension . ' file for test ' . $label);

				$this->assertTrue(
					$manager->canUnserializeFromFile($filename),
					$label . ' can unserialize .' . $extension);

				$data = $manager->unserializeFromFile($filename);

				if ($manager->canSerializeToFile($derivedFilename, $data))
				{
					$manager->serializeToFile($derivedFilename, $data);
					$this->assertFileExists($derivedFilename,
						$label . ' ' . $extension . ' serialized file');
					$data2 = $manager->unserializeFromFile(
						$derivedFilename);
					$this->assertEquals($data, $data2,
						$label . ' serialization cycle to ' . $extension);
				}
			}
		}
	}
}
