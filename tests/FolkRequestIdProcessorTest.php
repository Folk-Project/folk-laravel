<?php declare(strict_types=1);

namespace {
    // Stub the native folk_request_id() exposed by the Folk extension so the
    // processor can be exercised without the extension loaded. Driven by a global.
    if (!function_exists('folk_request_id')) {
        function folk_request_id(): string
        {
            return (string) ($GLOBALS['__folk_test_request_id'] ?? '');
        }
    }
}

namespace Folk\Laravel\Tests {

    use Folk\Laravel\Log\FolkRequestIdProcessor;
    use Monolog\Level;
    use Monolog\LogRecord;
    use PHPUnit\Framework\TestCase;

    class FolkRequestIdProcessorTest extends TestCase
    {
        private function record(): LogRecord
        {
            return new LogRecord(
                datetime: new \DateTimeImmutable('@0'),
                channel: 'test',
                level: Level::Info,
                message: 'hello',
            );
        }

        public function test_adds_request_id_when_present(): void
        {
            $GLOBALS['__folk_test_request_id'] = '017f22e2-79b0-7cc3-98c4-dc0c0c07398f';

            $record = (new FolkRequestIdProcessor())($this->record());

            $this->assertArrayHasKey('request_id', $record->extra);
            $this->assertSame('017f22e2-79b0-7cc3-98c4-dc0c0c07398f', $record->extra['request_id']);
        }

        public function test_omits_request_id_when_empty(): void
        {
            $GLOBALS['__folk_test_request_id'] = '';

            $record = (new FolkRequestIdProcessor())($this->record());

            $this->assertArrayNotHasKey('request_id', $record->extra);
        }

        public function test_preserves_existing_extra(): void
        {
            $GLOBALS['__folk_test_request_id'] = '0190aabb-ccdd-7eef-8001-0123456789ab';

            $base = $this->record()->with(extra: ['foo' => 'bar']);
            $record = (new FolkRequestIdProcessor())($base);

            $this->assertSame('bar', $record->extra['foo']);
            $this->assertSame('0190aabb-ccdd-7eef-8001-0123456789ab', $record->extra['request_id']);
        }
    }
}
