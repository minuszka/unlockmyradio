<?php

namespace Tests\Unit;

use App\Support\SerialClassifier;
use PHPUnit\Framework\TestCase;

class SerialClassifierTest extends TestCase
{
    public function testClassifiesContinentalVpFromEmbeddedA2C(): void
    {
        $classifier = new SerialClassifier();
        $result = $classifier->classify('Fiat 334 VP2 ECE NAV RVC A2C3827160100001193');

        self::assertSame('continental_vp', $result['family']);
        self::assertSame('1193', $result['lookup_serial']);
        self::assertSame('last4', $result['lookup_mode']);
    }

    public function testClassifiesFordMAndV(): void
    {
        $classifier = new SerialClassifier();

        $m = $classifier->classify('M123456');
        self::assertSame('ford_m', $m['family']);
        self::assertSame('M123456', $m['lookup_serial']);

        $v = $classifier->classify('V654321');
        self::assertSame('ford_v', $v['family']);
        self::assertSame('V654321', $v['lookup_serial']);
    }

    public function testClassifiesChryslerTWithLast5ThenLast4(): void
    {
        $classifier = new SerialClassifier();
        $result = $classifier->classify('TQ1AA1151A3424');

        self::assertSame('chrysler_t', $result['family']);
        self::assertSame('last5_then_last4', $result['lookup_mode']);
        self::assertSame(['13424', '3424'], $result['lookup_candidates']);
    }

    public function testClassifiesBeckerAndShortDigitModes(): void
    {
        $classifier = new SerialClassifier();

        $becker = $classifier->classify('BE1492 51138970');
        self::assertSame('becker', $becker['family']);
        self::assertSame('8970', $becker['lookup_serial']);

        $short4 = $classifier->classify('1234');
        self::assertSame('short_4digit', $short4['family']);

        $short5 = $classifier->classify('12345');
        self::assertSame('short_5digit', $short5['family']);
    }
}
