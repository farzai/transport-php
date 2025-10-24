<?php

declare(strict_types=1);

use Farzai\Transport\Factory\HttpFactory;
use Farzai\Transport\Multipart\MultipartStreamBuilder;
use Farzai\Transport\Multipart\Part;

beforeEach(function () {
    $this->httpFactory = HttpFactory::getInstance();
});

describe('Part', function () {
    it('creates a text field part', function () {
        $part = Part::text('username', 'john_doe');

        expect($part->getName())->toBe('username');
        expect($part->getContents())->toBe('john_doe');
        expect($part->isFile())->toBeFalse();
        expect($part->getFilename())->toBeNull();
    });

    it('creates a file part', function () {
        $content = 'file content';
        $part = Part::file('document', $content, 'test.txt', 'text/plain');

        expect($part->getName())->toBe('document');
        expect($part->getFilename())->toBe('test.txt');
        expect($part->isFile())->toBeTrue();
        expect($part->getHeader('Content-Type'))->toBe('text/plain');
    });

    it('generates correct Content-Disposition header for text field', function () {
        $part = Part::text('field', 'value');

        expect($part->getContentDisposition())->toBe('form-data; name="field"');
    });

    it('generates correct Content-Disposition header for file', function () {
        $part = Part::file('upload', 'content', 'document.pdf');

        expect($part->getContentDisposition())->toBe('form-data; name="upload"; filename="document.pdf"');
    });

    it('escapes quotes in field names and filenames', function () {
        $part = Part::file('field"name', 'content', 'file"name.txt');

        expect($part->getContentDisposition())->toContain('name="field\\"name"');
        expect($part->getContentDisposition())->toContain('filename="file\\"name.txt"');
    });

    it('guesses content type from file extension', function () {
        $tests = [
            'image.jpg' => 'image/jpeg',
            'document.pdf' => 'application/pdf',
            'data.json' => 'application/json',
            'archive.zip' => 'application/zip',
            'unknown.xyz' => 'application/octet-stream',
        ];

        foreach ($tests as $filename => $expectedType) {
            $part = Part::file('file', 'content', $filename);
            expect($part->getHeader('Content-Type'))->toBe($expectedType);
        }
    });

    it('returns size for string content', function () {
        $content = 'Hello World';
        $part = Part::text('field', $content);

        expect($part->getSize())->toBe(strlen($content));
    });

    it('allows custom headers', function () {
        $part = new Part('field', 'value', null, ['X-Custom' => 'test']);

        expect($part->getHeader('X-Custom'))->toBe('test');
    });
});

describe('MultipartStreamBuilder', function () {
    it('generates unique boundary', function () {
        $builder1 = new MultipartStreamBuilder;
        $builder2 = new MultipartStreamBuilder;

        expect($builder1->getBoundary())->not->toBe($builder2->getBoundary());
    });

    it('accepts custom boundary', function () {
        $boundary = 'custom-boundary-123';
        $builder = new MultipartStreamBuilder($boundary);

        expect($builder->getBoundary())->toBe($boundary);
    });

    it('adds text fields', function () {
        $builder = MultipartStreamBuilder::create();
        $builder->addField('name', 'John Doe')
            ->addField('email', 'john@example.com');

        expect($builder->count())->toBe(2);
        expect($builder->getParts()[0]->getName())->toBe('name');
        expect($builder->getParts()[1]->getName())->toBe('email');
    });

    it('builds valid multipart stream', function () {
        $builder = new MultipartStreamBuilder('boundary123');
        $builder->addField('username', 'johndoe');

        $stream = $builder->build();
        $content = $stream->getContents();

        expect($content)->toContain('--boundary123');
        expect($content)->toContain('Content-Disposition: form-data; name="username"');
        expect($content)->toContain('johndoe');
        expect($content)->toContain('--boundary123--');
    });

    it('returns correct Content-Type header', function () {
        $builder = new MultipartStreamBuilder('test-boundary');

        expect($builder->getContentType())->toBe('multipart/form-data; boundary=test-boundary');
    });

    it('adds file from contents', function () {
        $builder = MultipartStreamBuilder::create();
        $builder->addFileContents('document', 'PDF content here', 'report.pdf', 'application/pdf');

        $parts = $builder->getParts();
        expect($parts[0]->getName())->toBe('document');
        expect($parts[0]->getFilename())->toBe('report.pdf');
        expect($parts[0]->isFile())->toBeTrue();
    });

    it('adds multiple parts from array with simple format', function () {
        $builder = MultipartStreamBuilder::create();
        $builder->addMultiple([
            'name' => 'John',
            'age' => '30',
        ]);

        expect($builder->count())->toBe(2);
    });

    it('adds multiple parts from array with complex format', function () {
        $builder = MultipartStreamBuilder::create();
        $builder->addMultiple([
            ['name' => 'username', 'contents' => 'john'],
            ['name' => 'avatar', 'contents' => 'image data', 'filename' => 'avatar.jpg'],
        ]);

        $parts = $builder->getParts();
        expect($parts[0]->getName())->toBe('username');
        expect($parts[0]->isFile())->toBeFalse();
        expect($parts[1]->getName())->toBe('avatar');
        expect($parts[1]->isFile())->toBeTrue();
    });

    it('builds multipart stream with multiple parts', function () {
        $builder = new MultipartStreamBuilder('boundary456');
        $builder->addField('name', 'John Doe')
            ->addFileContents('file', 'content', 'test.txt');

        $stream = $builder->build();
        $content = $stream->getContents();

        // Check structure
        expect($content)->toContain('--boundary456');
        expect($content)->toContain('name="name"');
        expect($content)->toContain('John Doe');
        expect($content)->toContain('name="file"');
        expect($content)->toContain('filename="test.txt"');
        expect($content)->toContain('content');
        expect($content)->toContain('--boundary456--');
    });

    it('properly formats multipart boundaries and headers', function () {
        $builder = new MultipartStreamBuilder('test');
        $builder->addField('key', 'value');

        $stream = $builder->build();
        $content = $stream->getContents();

        // Check RFC 7578 compliance
        expect($content)->toStartWith('--test');
        expect($content)->toContain("\r\n");
        expect($content)->toEndWith("--test--\r\n");
    });

    it('creates builder with static factory method', function () {
        $builder = MultipartStreamBuilder::create('custom');

        expect($builder)->toBeInstanceOf(MultipartStreamBuilder::class);
        expect($builder->getBoundary())->toBe('custom');
    });

    it('handles content type aliases in array format', function () {
        $builder = MultipartStreamBuilder::create();
        $builder->addMultiple([
            ['name' => 'file', 'contents' => 'data', 'filename' => 'doc.pdf', 'contentType' => 'application/pdf'],
        ]);

        $part = $builder->getParts()[0];
        expect($part->getHeader('Content-Type'))->toBe('application/pdf');
    });

    it('counts parts correctly', function () {
        $builder = MultipartStreamBuilder::create();

        expect($builder->count())->toBe(0);

        $builder->addField('field1', 'value1');
        expect($builder->count())->toBe(1);

        $builder->addField('field2', 'value2');
        expect($builder->count())->toBe(2);
    });

    it('throws exception when adding non-readable file', function () {
        $builder = MultipartStreamBuilder::create();

        expect(fn () => $builder->addFile('file', '/non/existent/path.txt'))
            ->toThrow(\RuntimeException::class, 'File not readable');
    });
});

describe('MultipartStreamBuilder with real files', function () {
    beforeEach(function () {
        // Create a temporary test file
        $this->tempFile = sys_get_temp_dir().'/test_upload_'.uniqid().'.txt';
        file_put_contents($this->tempFile, 'Test file content');
    });

    afterEach(function () {
        // Clean up
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    });

    it('adds file from path', function () {
        $builder = MultipartStreamBuilder::create();
        $builder->addFile('document', $this->tempFile, 'custom.txt');

        $parts = $builder->getParts();
        expect($parts[0]->getFilename())->toBe('custom.txt');
        expect($parts[0]->isFile())->toBeTrue();
    });

    it('uses basename as filename if not provided', function () {
        $builder = MultipartStreamBuilder::create();
        $builder->addFile('document', $this->tempFile);

        $parts = $builder->getParts();
        expect($parts[0]->getFilename())->toBe(basename($this->tempFile));
    });

    it('builds valid multipart stream from file', function () {
        $builder = new MultipartStreamBuilder('testboundary');
        $builder->addFile('upload', $this->tempFile, 'myfile.txt');

        $stream = $builder->build();
        $content = $stream->getContents();

        expect($content)->toContain('Test file content');
        expect($content)->toContain('filename="myfile.txt"');
    });
});
