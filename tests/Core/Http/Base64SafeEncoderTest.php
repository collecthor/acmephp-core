<?php

/*
 * This file is part of the Acme PHP project.
 *
 * (c) Titouan Galopin <galopintitouan@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\AcmePhp\Core\Http;

use AcmePhp\Core\Http\Base64SafeEncoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Base64SafeEncoderTest extends TestCase
{
    #[DataProvider('getTestVectors')]
    public function testEncodeAndDecode(string $message, string $expected): void
    {
        $encoder = new Base64SafeEncoder();

        $encoded = $encoder->encode($message);
        $decoded = $encoder->decode($expected);

        $this->assertEquals($expected, $encoded);
        $this->assertEquals($message, $decoded);
    }

    /**
     * @see https://tools.ietf.org/html/rfc4648#section-10
     * @return list<array{0: string , 1:string}>
     */
    public static function getTestVectors(): array
    {
        return [
            [
                '000000', 'MDAwMDAw',
            ],
            [
                "\0\0\0\0", 'AAAAAA',
            ],
            [
                "\xff", '_w',
            ],
            [
                "\xff\xff", '__8',
            ],
            [
                "\xff\xff\xff", '____',
            ],
            [
                "\xff\xff\xff\xff", '_____w',
            ],
            [
                "\xfb", '-w',
            ],
            [
                '', '',
            ],
            [
                'foo', 'Zm9v',
            ],
            [
                'foobar', 'Zm9vYmFy',
            ],
        ];
    }

    #[DataProvider('getTestBadVectors')]
    public function testBadInput(string $input): void
    {
        $encoder = new Base64SafeEncoder();
        $decoded = $encoder->decode($input);
        $this->assertEquals("\00", $decoded);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function getTestBadVectors(): array
    {
        return [
            [
                ' AA',
            ],
            [
                "\tAA",
            ],
            [
                "\rAA",
            ],
            [
                "\nAA",
            ],
        ];
    }
}
