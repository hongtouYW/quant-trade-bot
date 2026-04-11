<?php

namespace Tests\Unit;

use App\Http\Helper;
use Tests\TestCase;

class HelperTest extends TestCase
{

    // Tests: Helper::isEnglishName()
    public function test_is_english_name_returns_true_for_english(): void
    {
        $this->assertTrue((bool) Helper::isEnglishName('JohnDoe123'));
    }

    // Tests: Helper::isEnglishName()
    public function test_is_english_name_returns_false_for_chinese(): void
    {
        $this->assertFalse((bool) Helper::isEnglishName('你好'));
    }

    // Tests: Helper::formatBytes()
    public function test_format_bytes(): void
    {
        $this->assertEquals('1KB', Helper::formatBytes(1024));
    }

    // Tests: Helper::formatBytes()
    public function test_format_bytes_megabytes(): void
    {
        $this->assertEquals('1MB', Helper::formatBytes(1048576));
    }

    // Tests: Helper::isValidJson()
    public function test_is_valid_json_with_valid_json(): void
    {
        $this->assertTrue(Helper::isValidJson('{"key":"value"}'));
    }

    // Tests: Helper::isValidJson()
    public function test_is_valid_json_with_invalid_string(): void
    {
        $this->assertFalse(Helper::isValidJson('not json'));
    }

    // Tests: Helper::getIp()
    public function test_get_ip_returns_string(): void
    {
        $result = Helper::getIp();

        $this->assertIsString($result);
    }
}
