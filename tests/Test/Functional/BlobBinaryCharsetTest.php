<?php

declare(strict_types=1);

namespace Satag\DoctrineFirebirdDriver\Test\Functional;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Satag\DoctrineFirebirdDriver\Compat\Override;
use Satag\DoctrineFirebirdDriver\Driver\Firebird\Middleware\CharsetMiddleware;
use Satag\DoctrineFirebirdDriver\Test\FunctionalTestCase;
use Throwable;

use function chr;
use function fopen;
use function fwrite;
use function mb_convert_encoding;
use function rewind;
use function str_repeat;
use function strpos;

/**
 * Integration test for BLOB binary data integrity through the charset middleware
 * with ISO8859_1 connection charset (the production config that causes #127).
 *
 * The default test suite uses UTF8 charset where mb_convert_encoding is a no-op,
 * giving false confidence. This test creates a separate connection with:
 *   - Firebird charset: ISO8859_1
 *   - CharsetMiddleware: ISO-8859-1 -> UTF-8
 *
 * Tests both write path (bindValue encoding) and read path (result decoding):
 * - Binary BLOB data (JPEG, PNG, etc.) must pass through untouched in both directions
 * - Text BLOB data must be transcoded from ISO-8859-1 <-> UTF-8
 *
 * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/127
 * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/128
 *
 * @psalm-suppress PropertyNotSetInConstructor - properties initialized in setUp()
 */
class BlobBinaryCharsetTest extends FunctionalTestCase
{
    private const TABLE_NAME = 'blob_binary_charset_test';

    private Connection $isoConn;

    /**
     * JPEG binary data must survive a full write→read roundtrip through
     * the ISO8859_1 charset middleware without corruption.
     *
     * Without the fix, the write path (CharsetStatementMiddleware::bindValue)
     * mangles \xFF\xD8\xFF\xE0 to ????, and the read path
     * (CharsetResultMiddleware::decodeValue) UTF-8 encodes the result.
     */
    public function testJpegRoundtripWriteRead(): void
    {
        $jpeg = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
            . "\xFF\xDB\x00\x43\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07"
            . "\x09\x09\x08\x0A\x0C\x14\x0D\x0C\x0B\x0B\x0C\x19\x12\x13\x0F";

        $this->assertBinaryBlobRoundtrip($jpeg);
    }

    /**
     * PNG binary data must survive write→read roundtrip.
     */
    public function testPngRoundtripWriteRead(): void
    {
        $png = "\x89PNG\x0D\x0A\x1A\x0A\x00\x00\x00\x0DIHDR"
            . "\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90\x77\x53\xDE";

        $this->assertBinaryBlobRoundtrip($png);
    }

    /**
     * All 256 byte values must survive write→read roundtrip.
     */
    public function testAll256BytesRoundtrip(): void
    {
        $binary = '';
        for ($i = 0; $i < 256; $i++) {
            $binary .= chr($i);
        }

        $this->assertBinaryBlobRoundtrip($binary);
    }

    /**
     * Large binary payload (64KB) with mixed data including NULL bytes.
     */
    public function testLargeBinaryBlobRoundtrip(): void
    {
        $chunk = '';
        for ($i = 0; $i < 256; $i++) {
            $chunk .= chr($i);
        }

        $binary = str_repeat($chunk, 256); // 65536 bytes

        $this->assertBinaryBlobRoundtrip($binary);
    }

    // -----------------------------------------------------------------------
    // Read-path only: raw binary in DB, verify middleware does not corrupt on fetch
    // -----------------------------------------------------------------------

    /**
     * Binary BLOB fetched via fetchOne (single value path) must not be transcoded.
     */
    public function testBinaryBlobInFetchOne(): void
    {
        $binary = "\x89PNG\x0D\x0A\x1A\x0A\x00\x00\x00\x0D";

        $this->insertBinaryBlob($binary, 300);
        $fetched = $this->isoConn->fetchOne(
            'SELECT binary_blob FROM ' . self::TABLE_NAME . ' WHERE id = 300',
        );

        self::assertIsString($fetched);
        self::assertSame($binary, $fetched, 'Binary BLOB via fetchOne must not be transcoded');
    }

    /**
     * Binary BLOB fetched via fetchAllAssociative (batch path) must not be transcoded.
     */
    public function testBinaryBlobInFetchAllAssociative(): void
    {
        $binary = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00";

        $this->insertBinaryBlob($binary, 400);
        $rows = $this->isoConn->fetchAllAssociative(
            'SELECT binary_blob FROM ' . self::TABLE_NAME . ' WHERE id = 400',
        );

        self::assertCount(1, $rows);
        self::assertArrayHasKey('BINARY_BLOB', $rows[0]);
        $fetched = $rows[0]['BINARY_BLOB'];
        self::assertIsString($fetched);
        self::assertSame($binary, $fetched, 'Binary BLOB via fetchAllAssociative must not be transcoded');
    }

    // -----------------------------------------------------------------------
    // Text BLOB transcoding (verify fix does not break text path)
    // -----------------------------------------------------------------------

    /**
     * Text BLOB with ISO-8859-1 umlauts must be correctly transcoded to UTF-8
     * on read, and UTF-8 text must be correctly transcoded to ISO-8859-1 on write.
     */
    public function testTextBlobRoundtripTranscoded(): void
    {
        // "Müller café" in UTF-8 (what the PHP application uses)
        $utf8Text = 'Müller café';

        $this->isoConn->insert(
            self::TABLE_NAME,
            ['id' => 500, 'binary_blob' => "\x00\x01\x02", 'text_blob' => $utf8Text],
            [
                'id' => ParameterType::INTEGER,
                'binary_blob' => ParameterType::LARGE_OBJECT,
                'text_blob' => ParameterType::STRING,
            ],
        );

        $row = $this->isoConn->fetchAssociative(
            'SELECT text_blob FROM ' . self::TABLE_NAME . ' WHERE id = 500',
        );

        self::assertIsArray($row);
        self::assertArrayHasKey('TEXT_BLOB', $row);
        self::assertSame($utf8Text, $row['TEXT_BLOB'], 'Text BLOB must be transcoded UTF-8 → ISO-8859-1 → UTF-8');
    }

    /**
     * Text with high bytes (> 0x7F) but no NULL bytes must be transcoded,
     * NOT passed through as binary. This verifies the heuristic boundary.
     */
    public function testHighByteTextWithoutNullIsTranscoded(): void
    {
        // ISO-8859-1 bytes 0x80-0xFF as UTF-8 multibyte sequences
        $utf8Text = mb_convert_encoding(
            str_repeat(chr(0xFC), 10), // ü × 10 in ISO-8859-1
            'UTF-8',
            'ISO-8859-1',
        );

        $this->isoConn->insert(
            self::TABLE_NAME,
            ['id' => 600, 'binary_blob' => "\x00", 'text_blob' => $utf8Text],
            [
                'id' => ParameterType::INTEGER,
                'binary_blob' => ParameterType::LARGE_OBJECT,
                'text_blob' => ParameterType::STRING,
            ],
        );

        $fetched = $this->isoConn->fetchOne(
            'SELECT text_blob FROM ' . self::TABLE_NAME . ' WHERE id = 600',
        );

        self::assertSame($utf8Text, $fetched, 'High-byte text without NULL must be transcoded');
    }

    /**
     * Binary BLOB with high bytes but no NULL bytes must pass through unchanged.
     *
     * Tests that sub_type detection correctly identifies sub_type 0 (BINARY)
     * even when the data contains no NULL bytes. Uses high-byte data (\xFF\xFE...)
     * that WOULD be corrupted by ISO-8859-1->UTF-8 transcoding, so the test
     * can distinguish between correct (pass-through) and incorrect (transcode)
     * behavior.
     *
     * With the old NULL-byte heuristic, high-byte data without NULLs was
     * incorrectly transcoded (corrupting it). With fbird_field_info() sub_type
     * detection (R1), sub_type 0 is detected definitively and passed through.
     *
     * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/148
     */
    public function testBinaryBlobWithoutNullBytesNotTranscodedWithSubType(): void
    {
        // High-byte binary data — no NULL bytes, but \xFF would be corrupted
        // by ISO-8859-1->UTF-8 transcoding (becomes \xC3\xBF).
        $binaryData = str_repeat("\xFF\xFE\xFD\xFC", 256); // 1024 bytes

        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $binaryData);
        rewind($stream);

        $this->isoConn->insert(
            self::TABLE_NAME,
            ['id' => 700, 'binary_blob' => $stream, 'text_blob' => 'placeholder'],
            [
                'id' => ParameterType::INTEGER,
                'binary_blob' => ParameterType::LARGE_OBJECT,
                'text_blob' => ParameterType::STRING,
            ],
        );

        $fetched = $this->isoConn->fetchOne(
            'SELECT binary_blob FROM ' . self::TABLE_NAME . ' WHERE id = 700',
        );

        self::assertIsString($fetched, 'BLOB content should be a string (FBIRD_FETCH_BLOBS)');
        self::assertSame(
            $binaryData,
            $fetched,
            'Binary BLOB without NULL bytes must not be transcoded (sub_type 0 detected via fbird_field_info)',
        );
    }

    /**
     * Text BLOB is correctly transcoded on read even when binary BLOB is in the
     * same result set (R1 sub_type detection per-column).
     *
     * Verifies that having a binary BLOB (sub_type 0) and a text BLOB (sub_type 1)
     * in the same SELECT does not confuse the per-column sub_type lookup.
     * The binary BLOB is passed through, the text BLOB is transcoded.
     */
    public function testMixedBinaryAndTextBlobInSameResultSet(): void
    {
        // Binary data with NULL bytes (would be detected by heuristic too)
        $binaryData = "\x00\xFF\xD8\xFF\xE0\x00";

        // UTF-8 text with high bytes (would be detected by heuristic too)
        $utf8Text = 'Müller café';

        $this->isoConn->insert(
            self::TABLE_NAME,
            ['id' => 800, 'binary_blob' => $binaryData, 'text_blob' => $utf8Text],
            [
                'id' => ParameterType::INTEGER,
                'binary_blob' => ParameterType::LARGE_OBJECT,
                'text_blob' => ParameterType::STRING,
            ],
        );

        $row = $this->isoConn->fetchAssociative(
            'SELECT binary_blob, text_blob FROM ' . self::TABLE_NAME . ' WHERE id = 800',
        );

        self::assertIsArray($row);
        self::assertArrayHasKey('BINARY_BLOB', $row);
        self::assertArrayHasKey('TEXT_BLOB', $row);
        self::assertSame(
            $binaryData,
            $row['BINARY_BLOB'],
            'Binary BLOB (sub_type 0) must pass through unchanged',
        );
        self::assertSame(
            $utf8Text,
            $row['TEXT_BLOB'],
            'Text BLOB (sub_type 1) must be transcoded ISO-8859-1 -> UTF-8',
        );
    }

    /**
     * Text BLOB containing NULL bytes must be transcoded, not treated as binary.
     *
     * Regression test: with the old NULL-byte heuristic, a text BLOB containing
     * a NULL byte was incorrectly detected as binary and passed through without
     * transcoding. With R1 sub_type detection, sub_type 1 (TEXT) is detected
     * definitively and transcoded regardless of byte content.
     *
     * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/149
     */
    public function testTextBlobWithNullBytesIsTranscoded(): void
    {
        // "Müller\x00café" in UTF-8 — contains a NULL byte inside text
        $utf8Text = "Müller\x00café";

        $this->isoConn->insert(
            self::TABLE_NAME,
            ['id' => 900, 'binary_blob' => "\x00\x01\x02", 'text_blob' => $utf8Text],
            [
                'id' => ParameterType::INTEGER,
                'binary_blob' => ParameterType::LARGE_OBJECT,
                'text_blob' => ParameterType::STRING,
            ],
        );

        $fetched = $this->isoConn->fetchOne(
            'SELECT text_blob FROM ' . self::TABLE_NAME . ' WHERE id = 900',
        );

        self::assertSame(
            $utf8Text,
            $fetched,
            'Text BLOB (sub_type 1) with NULL bytes must be transcoded, not treated as binary',
        );
    }

    /**
     * Binary BLOB fetched via fetchFirstColumn must not be transcoded.
     *
     * Tests the fetchFirstColumn path in CharsetResultMiddleware, which
     * passes column index 0 to decodeValue. Verifies sub_type detection
     * works correctly for this fetch mode.
     *
     * @see https://github.com/satwareAG/doctrine-firebird-driver/issues/152
     */
    public function testBinaryBlobInFetchFirstColumn(): void
    {
        $binary = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00";

        $this->insertBinaryBlob($binary, 1000);

        $column = $this->isoConn->fetchFirstColumn(
            'SELECT binary_blob FROM ' . self::TABLE_NAME . ' WHERE id = 1000',
        );

        self::assertCount(1, $column);
        self::assertIsString($column[0]);
        self::assertSame(
            $binary,
            $column[0],
            'Binary BLOB via fetchFirstColumn must not be transcoded',
        );
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Create a separate connection with ISO8859_1 charset + CharsetMiddleware
        /** @psalm-suppress InternalMethod - need params to clone connection config */
        $params            = $this->connection->getParams();
        $params['charset'] = 'ISO8859_1';

        $configuration = new Configuration();
        $configuration->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        $configuration->setMiddlewares([
            new CharsetMiddleware('ISO-8859-1', 'UTF-8'),
        ]);

        $params['persistent']                  = false;
        $params['driverOptions']['persistent'] = false;

        $this->isoConn = DriverManager::getConnection($params, $configuration);

        // Ensure test table exists
        $tableReady = false;
        try {
            $this->isoConn->executeStatement('DELETE FROM ' . self::TABLE_NAME);
            $tableReady = true;
        } catch (Throwable) {
            // Table doesn't exist yet
        }

        if ($tableReady) {
            return;
        }

        $table = Table::editor()
            ->setUnquotedName(self::TABLE_NAME)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('binary_blob')->setTypeName(Types::BLOB)->create(),
                Column::editor()->setUnquotedName('text_blob')->setTypeName(Types::TEXT)->create(),
            )
            ->create();
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );
        $this->isoConn->createSchemaManager()->createTable($table);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->markConnectionNotReusable();

        try {
            $this->isoConn->close();
        } catch (Throwable) {
        }

        parent::tearDown();
    }

// -----------------------------------------------------------------------

// Write-through roundtrip: insert binary via middleware, read back via middleware
// -----------------------------------------------------------------------


    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Insert binary data as stream resource through the full charset middleware
     * chain (write path), then fetch it back (read path) and assert integrity.
     *
     * This tests both the CharsetStatementMiddleware::bindValue() write-path
     * fix (NULL-byte skip on encode) and the CharsetResultMiddleware::decodeValue()
     * read-path fix (NULL-byte skip on decode).
     */
    private function assertBinaryBlobRoundtrip(string $binaryData): void
    {
        self::assertNotFalse(
            strpos($binaryData, "\x00"),
            'Test data must contain NULL byte to exercise both detection paths',
        );

        // Write through the middleware chain
        $this->insertBinaryBlob($binaryData, 1);

        // Read back through the middleware chain
        $fetched = $this->isoConn->fetchOne(
            'SELECT binary_blob FROM ' . self::TABLE_NAME . ' WHERE id = 1',
        );

        self::assertIsString($fetched, 'BLOB content should be a string (FBIRD_FETCH_BLOBS)');
        self::assertSame(
            $binaryData,
            $fetched,
            'Binary BLOB data must survive write→read roundtrip through ISO8859_1 charset middleware',
        );

        // Cleanup for next test method
        $this->isoConn->executeStatement('DELETE FROM ' . self::TABLE_NAME . ' WHERE id = 1');
    }

    /**
     * Insert binary data into the BLOB column via stream resource.
     */
    private function insertBinaryBlob(string $binaryData, int $id): void
    {
        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $binaryData);
        rewind($stream);

        $this->isoConn->insert(
            self::TABLE_NAME,
            ['id' => $id, 'binary_blob' => $stream, 'text_blob' => 'placeholder'],
            [
                'id' => ParameterType::INTEGER,
                'binary_blob' => ParameterType::LARGE_OBJECT,
                'text_blob' => ParameterType::STRING,
            ],
        );
    }
}
