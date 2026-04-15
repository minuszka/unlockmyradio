<?php

namespace Tests\Unit;

use App\Support\SerialClassifier;
use PHPUnit\Framework\TestCase;

class SerialClassifierTest extends TestCase
{
    public function testClassifiesFiatBpCmFormats(): void
    {
        $classifier = new SerialClassifier();

        $bp = $classifier->classify('BP630267648356');
        self::assertSame('fiat_bp_cm', $bp['family']);
        self::assertSame('BP630267648356', $bp['lookup_serial']);
        self::assertSame('exact', $bp['lookup_mode']);

        $cmWith815 = $classifier->classify('815CM0142D0810712');
        self::assertSame('fiat_bp_cm', $cmWith815['family']);
        self::assertSame('CM0142D0810712', $cmWith815['lookup_serial']);
        self::assertSame('exact', $cmWith815['lookup_mode']);

        $partial = $classifier->classify('CM01');
        self::assertSame('fiat_bp_cm', $partial['family']);
        self::assertNull($partial['lookup_serial']);
        self::assertSame('exact_pending', $partial['lookup_mode']);
    }

    public function testClassifiesContinentalVpFromEmbeddedA2C(): void
    {
        $classifier = new SerialClassifier();
        $result = $classifier->classify('Fiat 334 VP2 ECE NAV RVC A2C3827160100001193');

        self::assertSame('continental_vp', $result['family']);
        self::assertSame('1193', $result['lookup_serial']);
        self::assertSame('last4', $result['lookup_mode']);
    }

    public function testClassifiesShortA2CPrefixBeforeLookupIsReady(): void
    {
        $classifier = new SerialClassifier();
        $result = $classifier->classify('A2C9');

        self::assertSame('continental_vp', $result['family']);
        self::assertNull($result['lookup_serial']);
        self::assertSame('last4_pending', $result['lookup_mode']);

        $stillShort = $classifier->classify('A2C123456789');
        self::assertSame('continental_vp', $stillShort['family']);
        self::assertNull($stillShort['lookup_serial']);
        self::assertSame('last4_pending', $stillShort['lookup_mode']);
    }

    public function testClassifiesFordMAndV(): void
    {
        $classifier = new SerialClassifier();

        $m = $classifier->classify('M123456');
        self::assertSame('ford_m', $m['family']);
        self::assertSame('M123456', $m['lookup_serial']);
        self::assertSame('Ford / Fiat Visteon', $m['brand_hint']);

        $v = $classifier->classify('V654321');
        self::assertSame('ford_v', $v['family']);
        self::assertSame('V654321', $v['lookup_serial']);
    }

    public function testClassifiesShortFordPrefixAsPending(): void
    {
        $classifier = new SerialClassifier();

        $m = $classifier->classify('M12');
        self::assertSame('ford_m', $m['family']);
        self::assertNull($m['lookup_serial']);
        self::assertSame('exact_pending', $m['lookup_mode']);

        $v = $classifier->classify('V12');
        self::assertSame('ford_v', $v['family']);
        self::assertNull($v['lookup_serial']);
        self::assertSame('exact_pending', $v['lookup_mode']);
    }

    public function testClassifiesFiatVisteonMWhenFiatContextPresent(): void
    {
        $classifier = new SerialClassifier();

        $result = $classifier->classify('Fiat Stilo Visteon M007025');

        self::assertSame('fiat_visteon_m', $result['family']);
        self::assertSame('M007025', $result['lookup_serial']);
        self::assertSame('exact', $result['lookup_mode']);
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
