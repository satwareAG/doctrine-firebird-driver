<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;

use function base64_encode;
use function chr;
use function fopen;
use function str_repeat;
use function stream_get_contents;
use function strlen;

/** @see https://github.com/satwareAG/doctrine-firebird-driver/issues/91 */
class BlobCharsetIntegrityTest extends FunctionalTestCase
{
    public function testNullBytesNotCorrupted(): void
    {
        $binary = "hello\x00world\x00\x00final";
        $result = $this->insertAndFetchBlob($binary);

        self::assertIsResource($result);
        self::assertSame($binary, stream_get_contents($result));
    }

    public function testNullBytesOnlyBlob(): void
    {
        $binary = "\x00\x00\x00\x00\x00";
        $result = $this->insertAndFetchBlob($binary);

        self::assertIsResource($result);
        self::assertSame($binary, stream_get_contents($result));
    }

    public function testHighByteSequencesNotCorrupted(): void
    {
        $binary = '';
        for ($i = 0x80; $i <= 0xFF; $i++) {
            $binary .= chr($i);
        }

        $result = $this->insertAndFetchBlob($binary);

        self::assertIsResource($result);
        self::assertSame($binary, stream_get_contents($result));
    }

    public function testAllByteValuesPreserved(): void
    {
        $binary = '';
        for ($i = 0; $i < 256; $i++) {
            $binary .= chr($i);
        }

        $result = $this->insertAndFetchBlob($binary);

        self::assertIsResource($result);
        $fetched = stream_get_contents($result);
        self::assertSame(256, strlen($fetched));

        for ($i = 0; $i < 256; $i++) {
            self::assertSame(chr($i), $fetched[$i], 'Byte at offset ' . $i . ' does not match');
        }
    }

    public function testLargeBinaryBlobIntegrity(): void
    {
        // 64KB of mixed binary data
        $chunk = '';
        for ($i = 0; $i < 256; $i++) {
            $chunk .= chr($i);
        }

        $binary = str_repeat($chunk, 256); // 256 * 256 = 65536 bytes

        $result = $this->insertAndFetchBlob($binary);

        self::assertIsResource($result);
        self::assertSame(65536, strlen(stream_get_contents($result)));
    }

    public function testRoundtripWriteReadIntegrity(): void
    {
        // Realistic binary payload: PDF header + random high bytes + null padding
        $binary = "%PDF-1.4\x00\x01\x02\x03\xff\xfe\xfd\xfc\x00\x00"
            . "\x80\x81\x82\x83\xc0\xc1\xd0\xd1\xe0\xe1\xf0\xf1"
            . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";

        $result = $this->insertAndFetchBlob($binary);

        self::assertIsResource($result);
        self::assertSame($binary, stream_get_contents($result));
    }

    public function testInterleavedNullAndHighBytes(): void
    {
        $binary = "\x00\xff\x00\x80\x00\xc0\x00\xd0\x00\xe0\x00\xf0"
            . "\x80\x00\xff\x00\xfe\x00\xfd\x00\xfc\x00\xfb\x00";

        $result = $this->insertAndFetchBlob($binary);

        self::assertIsResource($result);
        self::assertSame($binary, stream_get_contents($result));
    }

    protected function setUp(): void
    {
        $table = new Table('blob_charset_test');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('data', Types::BLOB);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);
    }

    protected function tearDown(): void
    {
        if ($this->connection->isConnected()) {
            $this->markConnectionNotReusable();
        }

        parent::tearDown();
    }

    /** @return resource */
    private function insertAndFetchBlob(string $binaryData)
    {
        $stream = fopen('data://application/octet-stream;base64,' . base64_encode($binaryData), 'r');
        $this->connection->insert(
            'blob_charset_test',
            ['id' => 1, 'data' => $stream],
            ['id' => ParameterType::INTEGER, 'data' => ParameterType::LARGE_OBJECT],
        );

        $rows = $this->connection->fetchAllNumeric('SELECT data FROM blob_charset_test WHERE id = 1');
        self::assertCount(1, $rows);

        $blobType = Type::getType(Types::BLOB);

        return $blobType->convertToPHPValue($rows[0][0], $this->connection->getDatabasePlatform());
    }
}
