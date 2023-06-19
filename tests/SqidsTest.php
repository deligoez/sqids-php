<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Sqids\Sqids;

class SqidsTest extends TestCase
{
    public function test_incremental_numbers()
    {
        $sqids = new Sqids();

        $this->assertSame('vn', $sqids->encode([0]));
        $this->assertSame('et', $sqids->encode([1]));
        $this->assertSame('ni', $sqids->encode([2]));
        $this->assertSame('g6', $sqids->encode([3]));
        $this->assertSame('tP', $sqids->encode([4]));
    }

    public function test_incremental_numbers_same_prefix()
    {
        $sqids = new Sqids();

        $this->assertSame('egvK', $sqids->encode([0, 0]));
        $this->assertSame('ghnJ', $sqids->encode([0, 1]));
        $this->assertSame('hjts', $sqids->encode([0, 2]));
        $this->assertSame('jCi1', $sqids->encode([0, 3]));
        $this->assertSame('Cm6u', $sqids->encode([0, 4]));
    }

    public function test_incremental_numbers_same_postfix()
    {
        $sqids = new Sqids();

        $this->assertSame('egvK', $sqids->encode([0, 0]));
        $this->assertSame('nhet', $sqids->encode([1, 0]));
        $this->assertSame('gjnh', $sqids->encode([2, 0]));
        $this->assertSame('tCgH', $sqids->encode([3, 0]));
        $this->assertSame('hmtj', $sqids->encode([4, 0]));
    }

    public function test_decoding()
    {
        $sqids = new Sqids();

        $this->assertSame([0, 0], $sqids->decode('egvK'));
        $this->assertSame([0, 1], $sqids->decode('ghnJ'));
        $this->assertSame([0, 2], $sqids->decode('hjts'));
        $this->assertSame([0, 3], $sqids->decode('jCi1'));
        $this->assertSame([0, 4], $sqids->decode('Cm6u'));
    }

    public function test_minimum_length()
    {
        $alphabetLength = strlen('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890');

        foreach ([1, 10, $alphabetLength] as $minLength) {
            foreach ([
                [0],
                [1, 2, 3, 4, 5],
                [100, 200, 300],
                [1000, 2000, 3000],
                [1000000]
            ] as $numbers) {
                $sqids = new Sqids(
                    minLength: $minLength
                );

                $id = $sqids->encode($numbers);
                $this->assertGreaterThanOrEqual($minLength, strlen($id));
                $this->assertSame($numbers, $sqids->decode($id));
            }
        }
    }
    public function test_blocklist()
    {
        $sqids = new Sqids(
            blocklist: [
                'syrjLE', // result of the 1st encoding
                'zkleEBnG', // result of the 2nd encoding
                'DkXJ5q' // result of the 3rd encoding is "lDkXJ5q5", but let's check substring
            ]
        );

        $this->assertSame('kQBclabQ', $sqids->encode([1, 2, 3]));
        $this->assertSame([1, 2, 3], $sqids->decode('kQBclabQ'));
    }

    public function test_encoding_decoding()
    {
        $sqids = new Sqids();

        $numbers = [
            0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25,
            26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49,
            50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73,
            74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92, 93, 94, 95, 96, 97,
            98, 99
        ];
        $output = $sqids->decode($sqids->encode($numbers));
        $this->assertSame($numbers, $output);
    }

    public function test_uniques()
    {
        $sqids = new Sqids();
        $max = 1000000;
        $set = [];

        for ($i = 0; $i != $max; $i++) {
            $id = $sqids->encode([$i]);
            $set[$id] = true;
            $this->assertSame([$i], $sqids->decode($id));
        }

        $this->assertSame($max, count($set));
    }
}
