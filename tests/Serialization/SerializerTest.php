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
use NoreSources\Data\Serialization\SerializationManager;
use NoreSources\Data\Serialization\DataSerializationManager;
use NoreSources\Data\Serialization\IniSerializer;
use NoreSources\Data\Serialization\UrlEncodedSerializer;
use NoreSources\Data\Serialization\CsvSerializer;
use NoreSources\Type\TypeDescription;
use NoreSources\Data\Serialization\PhpFileUnserializer;
use NoreSources\Data\Serialization\YamlSerializer;
use NoreSources\Data\Serialization\Traits\DataFileUnserializerTrait;
use NoreSources\Data\Serialization\DataFileUnerializerInterface;
use NoreSources\Data\Serialization\JsonSerializer;
use NoreSources\Data\Serialization\LuaSerializer;
use NoreSources\MediaType\MediaType;
use NoreSources\MediaType\MediaTypeFactory;
use NoreSources\Data\Serialization\PlainTextSerializer;
use NoreSources\Type\TypeConversion;

final class SerializerTest extends \PHPUnit\Framework\TestCase
{

	public function testSerializePlain()
	{
		$s = new PlainTextSerializer();
		$tests = [
			'basic' => [
				'input' => 'Hello',
				'expected' => 'Hello'
			],
			'int' => [
				'input' => 42,
				'expected' => '42'
			],
			'boolean false' => [
				'input' => false,
				'expected' => ''
			],
			'boolean true' => [
				'input' => true,
				'expected' => '1'
			],
			'list' => [
				'input' => [
					'a',
					'b'
				],
				'expected' => "a\nb"
			],
			'map' => [
				'input' => [
					'First' => 'a',
					'Second' => 'b'
				],
				'expected' => "a\nb"
			],
			'deep map' => [
				'input' => [
					'First' => 'a',
					'Second' => [
						's1' => 'b1',
						'b2' => [
							'c1',
							'c2' => [
								'foo' => 'bar'
							],
							'c3'
						]
					]
				],
				'expected' => "a\nb1\nc1\nbar\nc3"
			]
		];

		foreach ($tests as $label => $test)
		{
			$i = $test['input'];
			$expected = $test['expected'];
			$actual = $s->serializeData($i);
			$this->assertEquals($expected, $actual, $label);
		}
	}

	public function testUnserializePlain()
	{
		$s = new PlainTextSerializer();
		$tests = [
			'text' => [
				'input' => 'Hello',
				'expected' => 'Hello'
			],
			'int' => [
				'input' => '42',
				'expected' => 42
			],
			'float' => [
				'input' => '6.55957',
				'expected' => 6.55957
			],
			'LF' => [
				"input" => "first\nsecond\nthird",
				'expected' => [
					'first',
					'second',
					'third'
				]
			],
			'Mixed CR, LF and CRLF' => [
				"input" => "first\nInner\rValues\r\nsecond\nthird",
				'expected' => [
					'first',
					'Inner',
					'Values',
					'second',
					'third'
				]
			]
		];

		foreach ($tests as $label => $test)
		{
			$i = $test['input'];
			$expected = $test['expected'];
			$actual = $s->unserializeData($i);

			$this->assertEquals(TypeDescription::getName($expected),
				TypeDescription::getName($actual),
				$label . ' to ' . TypeDescription::getName($expected));

			$this->assertEquals($expected, $actual, $label . ' value');
		}
	}

	public function testLua()
	{
		$directory = __DIR__ . '/../data';
		$tests = [
			'nil' => null,
			'true' => true,
			'false' => false,
			'pi' => 3.14,
			'answer' => 42,
			'table' => [
				"key" => "value",
				"implicitely indexed",
				"subtree" => [
					5,
					6,
					7
				],
				'Not an identifier' => 'Somthing "in" the air',
				"05" => "It's not '5'"
			]
		];

		$serializer = new LuaSerializer();
		$mediaType = MediaType::createFromString('text/x-lua');

		foreach ($tests as $key => $data)
		{
			$filename = $directory . '/' . $key . '.data.lua';

			$this->assertTrue($serializer->canSerializeData($data),
				'Can serialize ' . TypeDescription::getName($data) . ' ' .
				$key);

			$serialized = $serializer->serializeData($data, $mediaType);
			if (!\is_file($filename))
				\file_put_contents($filename, $serialized);
			$reference = file_get_contents($filename);

			$this->assertEquals($reference, $serialized, $key);
		}
	}

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
		$mediaType = MediaType::createFromString(
			'application/x-www-form-urlencoded');

		foreach ([
			'Text' => 'text',
			'A text with space' => 'A text with space',
			'Key-values' => [
				'key' => 'value',
				'Complex' => 'A more "tricky" string'
			]
		] as $label => $value)
		{
			$this->assertTrue(
				$serializer->canSerializeData($value, $mediaType),
				'Can serialize ' . $label . ' with media type');
			$this->assertTrue($serializer->canSerializeData($value),
				'Can serialize ' . $label . ' without media type');

			$serialized = $serializer->serializeData($value);

			$this->assertTrue(
				$serializer->canUnserializeData($serialized, $mediaType),
				'Can unserialize ' . $label . ' ' .
				TypeDescription::getName($serialized) .
				' with media type');

			$this->assertTrue(
				$serializer->canUnserializeData($serialized),
				'Can unserialize ' . $label . ' ' .
				TypeDescription::getName($serialized) .
				' without media type');

			$unserialized = $serializer->unserializeData($serialized);

			$this->assertEquals($value, $unserialized,
				'Serialization/Deserialization cycle');
		}
	}

	public function testFile()
	{
		$extensions = [
			'php' => PhpFileUnserializer::class,
			'ini' => IniSerializer::class,
			'csv' => CsvSerializer::class
		];
		if (\extension_loaded('json'))
			$extensions['json'] = JsonSerializer::class;
		if (\extension_loaded('yaml'))
			$extensions['yaml'] = YamlSerializer::class;

		$filenameBase = __DIR__ . '/../data/table.';

		foreach ($extensions as $x => $classname)
		{
			$filename = $filenameBase . $x;
			$cls = new \ReflectionClass($classname);
			/** @var DataFileUnerializerInterface  $serializer */
			$serializer = $cls->newInstance();

			$this->assertFileExists($filename);

			$this->assertTrue(
				$serializer->canUnserializeFromFile($filename),
				\basename($filename) . ' ' .
				\strval(MediaTypeFactory::createFromMedia($filename)) .
				' ' . $x . ' support using ' .
				TypeDescription::getLocalName($classname, true));

			$actual = $serializer->unserializeFromFile($filename);

			$actual = Container::map($actual,
				function ($k, $v) {
					if (\is_array($v))
						return \implode(', ', $v);
					return $v;
				});

			if (isset($expected))
			{
				$this->assertEquals($expected, $actual,
					'Compare ' . $x . ' vs ' . $expectedExtension);
			}
			else
			{
				$expected = $actual;
				$expectedExtension = $x;
			}
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

				if ($manager->canSerializeData($data))
				{
					$serialized = $manager->serializeData($data);
					$this->assertEquals('string',
						TypeDescription::getName($serialized),
						'Re-serialize');
					if ($manager->canUnserializeData($serialized))
					{
						$data = $manager->unserializeData($serialized);
					}
				}

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
