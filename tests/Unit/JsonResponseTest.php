<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use PHPUnit\Framework\TestCase;

/**
 * Kontrak respons API-01: application/json dengan kode status yang dipilih.
 */
final class JsonResponseTest extends TestCase
{
    public function testJsonResponseHasContentTypeStatusAndReadableUnicode(): void
    {
        $response = Response::json(['error' => 'not_found', 'name' => 'Monitor LED 24" — Gudang Surabaya'], 404);

        self::assertSame(404, $response->status);
        self::assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
        self::assertSame('no-store', $response->headers['Cache-Control']);
        self::assertSame('{"error":"not_found","name":"Monitor LED 24\" — Gudang Surabaya"}', $response->body);
        self::assertSame(['error' => 'not_found', 'name' => 'Monitor LED 24" — Gudang Surabaya'], json_decode($response->body, true));
    }
}
