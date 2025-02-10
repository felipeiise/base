<?php

declare(strict_types=1);

require_once 'SpringCourier.php';

use PHPUnit\Framework\TestCase;
use SpringCourier\SpringCourier;

class SpringCourierTest extends TestCase
{
    protected SpringCourier $courier;

    protected function setUp(): void
    {
        $this->courier = new SpringCourier();
    }

    public function testEmptyAddressReturnsError(): void
    {
        $address = '';
        $result = $this->courier->splitAddress($address, 'PPLEU');
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Address cannot be empty.', $result['error']);
    }

    // Test cases for SOFT LIMIT
    public function testSoftLimitWithShortAddress(): void
    {
        $address = 'Short address';
        $result = $this->courier->splitAddress($address, 'PPLEU');
        $this->assertEquals([
            'AddressLine1' => 'Short address'
        ], $result);
    }

    public function testSoftLimitWithExactSoftLimitLength(): void
    {
        $address = 'This address is exactly thirty-five';
        $result = $this->courier->splitAddress($address, 'PPLEU');
        $this->assertEquals([
            'AddressLine1' => 'This address is exactly thirty-five'
        ], $result);
    }

    public function testSoftLimitWithLongAddress(): void
    {
        $address = 'This is a very long address that needs to be split properly according to the soft limit rule';
        $result = $this->courier->splitAddress($address, 'PPLEU');
        $this->assertEquals([
            'AddressLine1' => 'This is a very long address that needs',
            'AddressLine2' => 'to be split properly according to the',
            'AddressLine3' => 'soft limit rule'
        ], $result);
    }

    public function testSoftLimitWithNoSpaces(): void
    {
        $address = 'ThisIsAnAddressWithNoSpacesAndItShouldBeSplitByLength';
        $result = $this->courier->splitAddress($address, 'PPLEU');
        $this->assertEquals([
            'AddressLine1' => 'ThisIsAnAddressWithNoSpacesAndItSho',
            'AddressLine2' => 'uldBeSplitByLength'
        ], $result);
    }

    // Test cases for HARD LIMIT
    public function testHardLimitWithShortAddress(): void
    {
        $address = 'Short address';
        $result = $this->courier->splitAddress($address, 'RM24/48(S)');
        $this->assertEquals([
            'AddressLine1' => 'Short address'
        ], $result);
    }

    public function testHardLimitWithExactHardLimitLength(): void
    {
        $address = 'This address is exactly thirty-five';
        $result = $this->courier->splitAddress($address, 'RM24/48(S)');
        $this->assertEquals([
            'AddressLine1' => 'This address is exactly thirty-five'
        ], $result);
    }

    public function testHardLimitWithLongAddress(): void
    {
        $address = 'This is a very long address that needs to be split properly according to the hard limit rule';
        $result = $this->courier->splitAddress($address, 'RM24/48(S)');
        $this->assertEquals([
            'AddressLine1' => 'This is a very long address that',
            'AddressLine2' => 'needs to be split properly',
            'AddressLine3' => 'according to the hard limit rule'
        ], $result);
    }

    public function testHardLimitWithNoSpaces(): void
    {
        $address = 'ThisIsAnAddressWithNoSpacesAndItShouldBeSplitByLength';
        $result = $this->courier->splitAddress($address, 'RM24/48(S)');
        $this->assertEquals([
            'AddressLine1' => 'ThisIsAnAddressWithNoSpacesAndItSho',
            'AddressLine2' => 'uldBeSplitByLength'
        ], $result);
    }

    public function testHardLimitWithExceedingTotalHardLimit(): void
    {
        $address = 'This address is way too long and exceeds the total hard limit of 105 characters for three lines with
         a hard limit of 35 each';
        $result = $this->courier->splitAddress($address, 'RM24/48(S)');
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals(
            'Address exceeds the total hard limit. Please shorten the address.',
            $result['error']
        );
    }

    // Test cases for mixed scenarios
    public function testMixedSoftAndHardLimit(): void
    {
        $address = 'This address is long enough to test both soft and hard limits in different scenarios';
        $resultSoft = $this->courier->splitAddress($address, 'PPLEU');
        $resultHard = $this->courier->splitAddress($address, 'RM24/48(S)');

        $this->assertEquals([
            'AddressLine1' => 'This address is long enough to test',
            'AddressLine2' => 'both soft and hard limits in different',
            'AddressLine3' => 'scenarios'
        ], $resultSoft);

        $this->assertEquals([
            'AddressLine1' => 'This address is long enough to',
            'AddressLine2' => 'test both soft and hard limits in',
            'AddressLine3' => 'different scenarios'
        ], $resultHard);
    }

    public function testInvalidServiceKeyThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Service key 'INVALID_KEY' not found.");

        // Attempt to split an address with an invalid service key
        $this->courier->splitAddress('Valid address', 'INVALID_KEY');
    }

    public function testValidServiceKeyDoesNotThrowException(): void
    {
        // Attempt to split an address with a valid service key
        $result = $this->courier->splitAddress('Valid address', 'PPLEU');

        // Assert that no exception is thrown and the result is as expected
        $this->assertEquals([
            'AddressLine1' => 'Valid address'
        ], $result);
    }
}