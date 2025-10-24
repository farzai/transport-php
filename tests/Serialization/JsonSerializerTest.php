<?php

declare(strict_types=1);

use Farzai\Transport\Exceptions\JsonEncodeException;
use Farzai\Transport\Exceptions\JsonParseException;
use Farzai\Transport\Serialization\JsonConfig;
use Farzai\Transport\Serialization\JsonSerializer;

describe('JsonSerializer', function () {
    it('encodes simple data to JSON', function () {
        $serializer = new JsonSerializer;
        $data = ['name' => 'John', 'age' => 30];

        $result = $serializer->encode($data);

        expect($result)->toBeString()
            ->and(json_decode($result, true))->toBe($data);
    });

    it('decodes JSON string to array', function () {
        $serializer = new JsonSerializer;
        $json = '{"name":"John","age":30}';

        $result = $serializer->decode($json);

        expect($result)->toBe(['name' => 'John', 'age' => 30]);
    });

    it('decodes nested JSON using dot notation', function () {
        $serializer = new JsonSerializer;
        $json = '{"user":{"name":"John","address":{"city":"NYC"}}}';

        expect($serializer->decode($json, 'user.name'))->toBe('John')
            ->and($serializer->decode($json, 'user.address.city'))->toBe('NYC');
    });

    it('returns null for empty string', function () {
        $serializer = new JsonSerializer;

        expect($serializer->decode(''))->toBeNull();
    });

    it('throws JsonParseException on invalid JSON', function () {
        $serializer = new JsonSerializer;

        $serializer->decode('{"invalid": json}');
    })->throws(JsonParseException::class);

    it('decodeOrNull returns null on invalid JSON', function () {
        $serializer = new JsonSerializer;

        expect($serializer->decodeOrNull('{"invalid": json}'))->toBeNull();
    });

    it('handles large integers with BIGINT_AS_STRING', function () {
        $serializer = new JsonSerializer;
        // Use a number larger than PHP_INT_MAX to ensure it's converted to string
        $json = '{"bigNumber": 99999999999999999999}';

        $result = $serializer->decode($json);

        // Should be converted to string to prevent overflow
        expect($result['bigNumber'])->toBeString()
            ->and($result['bigNumber'])->toBe('99999999999999999999');
    });

    it('uses unescaped slashes by default', function () {
        $serializer = new JsonSerializer;
        $data = ['url' => 'https://example.com/path'];

        $result = $serializer->encode($data);

        expect($result)->toContain('https://example.com/path')
            ->and($result)->not->toContain('\\/');
    });

    it('uses unescaped unicode by default', function () {
        $serializer = new JsonSerializer;
        $data = ['text' => 'Hello 世界'];

        $result = $serializer->encode($data);

        expect($result)->toContain('世界');
    });

    it('respects max depth configuration', function () {
        $config = JsonConfig::default()->withMaxDepth(2);
        $serializer = new JsonSerializer($config);

        // Create deeply nested data (depth > 2)
        $data = ['level1' => ['level2' => ['level3' => 'value']]];

        $serializer->encode($data);
    })->throws(JsonEncodeException::class);

    it('can use pretty print configuration', function () {
        $config = JsonConfig::prettyPrint();
        $serializer = new JsonSerializer($config);
        $data = ['name' => 'John', 'age' => 30];

        $result = $serializer->encode($data);

        expect($result)->toContain("\n")
            ->and($result)->toContain('    '); // Indentation
    });

    it('returns correct content type', function () {
        $serializer = new JsonSerializer;

        expect($serializer->getContentType())->toBe('application/json');
    });

    it('exposes configuration', function () {
        $config = JsonConfig::strict();
        $serializer = new JsonSerializer($config);

        expect($serializer->getConfig())->toBe($config);
    });

    it('handles array conversion to associative array by default', function () {
        $serializer = new JsonSerializer;
        $json = '["a","b","c"]';

        $result = $serializer->decode($json);

        expect($result)->toBe(['a', 'b', 'c'])
            ->and($result)->toBeArray();
    });

    it('can decode as object when configured', function () {
        $config = JsonConfig::default()->withAssociative(false);
        $serializer = new JsonSerializer($config);
        $json = '{"name":"John"}';

        $result = $serializer->decode($json);

        expect($result)->toBeObject()
            ->and($result->name)->toBe('John');
    });

    it('provides detailed error information in exceptions', function () {
        $serializer = new JsonSerializer;
        $invalidJson = '{"invalid": }';

        try {
            $serializer->decode($invalidJson);
            expect(true)->toBeFalse(); // Should not reach here
        } catch (JsonParseException $e) {
            expect($e->jsonString)->toBe($invalidJson)
                ->and($e->jsonErrorCode)->toBeInt()
                ->and($e->jsonErrorMessage)->toBeString()
                ->and($e->depth)->toBe(512)
                ->and($e->format)->toBe('JSON');
        }
    });

    it('handles encoding errors gracefully', function () {
        $serializer = new JsonSerializer;

        // Create a resource that cannot be JSON encoded
        $resource = fopen('php://memory', 'r');

        try {
            $serializer->encode(['resource' => $resource]);
            expect(true)->toBeFalse(); // Should not reach here
        } catch (JsonEncodeException $e) {
            expect($e->jsonErrorCode)->toBeInt()
                ->and($e->jsonErrorMessage)->toBeString()
                ->and($e->format)->toBe('JSON');
        } finally {
            fclose($resource);
        }
    });

    it('handles null values correctly', function () {
        $serializer = new JsonSerializer;
        $data = ['value' => null];

        $encoded = $serializer->encode($data);
        $decoded = $serializer->decode($encoded);

        expect($decoded)->toBe($data);
    });

    it('handles empty arrays correctly', function () {
        $serializer = new JsonSerializer;
        $data = [];

        $encoded = $serializer->encode($data);
        $decoded = $serializer->decode($encoded);

        expect($decoded)->toBe($data);
    });

    it('extracts nested array values with dot notation', function () {
        $serializer = new JsonSerializer;
        $json = '{"items":[{"id":1,"name":"First"},{"id":2,"name":"Second"}]}';

        expect($serializer->decode($json, 'items.0.name'))->toBe('First')
            ->and($serializer->decode($json, 'items.1.id'))->toBe(2);
    });

    it('can extract values from object data with dot notation', function () {
        $config = JsonConfig::default()->withAssociative(false);
        $serializer = new JsonSerializer($config);
        $json = '{"user":{"name":"John","email":"john@example.com"}}';

        $result = $serializer->decode($json, 'user.name');

        expect($result)->toBe('John');
    });

    it('returns scalar value when key is empty string', function () {
        $serializer = new JsonSerializer;
        $json = '"just a string"';

        $result = $serializer->decode($json, '');

        expect($result)->toBe('just a string');
    });

    it('returns null for scalar value with non-empty key', function () {
        $serializer = new JsonSerializer;
        $json = '"just a string"';

        $result = $serializer->decode($json, 'some.key');

        expect($result)->toBeNull();
    });

    it('handles integer scalar with key extraction', function () {
        $serializer = new JsonSerializer;
        $json = '42';

        expect($serializer->decode($json, ''))->toBe(42)
            ->and($serializer->decode($json, 'key'))->toBeNull();
    });

    it('handles boolean scalar with key extraction', function () {
        $serializer = new JsonSerializer;
        $json = 'true';

        expect($serializer->decode($json, ''))->toBeTrue()
            ->and($serializer->decode($json, 'key'))->toBeNull();
    });
});
