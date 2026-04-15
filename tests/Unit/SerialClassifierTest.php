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

    public function testClassifiesContinentalVpFromFullA2C(): void
    {
        $classifier = new SerialClassifier();
        $result = $classifier->classify('A2C3827160100001193');

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

    public function testDoesNotClassifyInnerFordPatternWithLeadingChars(): void
    {
        $classifier = new SerialClassifier();

        $pv = $classifier->classify('PV123');
        self::assertSame('unknown', $pv['family']);

        $bm = $classifier->classify('BM123');
        self::assertSame('unknown', $bm['family']);

        $mTooLong = $classifier->classify('M1234567');
        self::assertSame('unknown', $mTooLong['family']);

        $vTooLong = $classifier->classify('V1234567');
        self::assertSame('unknown', $vTooLong['family']);
    }

    public function testClassifiesFiatVisteonMWhenFiatContextPresent(): void
    {
        $classifier = new SerialClassifier();

        $result = $classifier->classify('Fiat Stilo Visteon M007025');

        self::assertSame('fiat_visteon_m', $result['family']);
        self::assertSame('M007025', $result['lookup_serial']);
        self::assertSame('exact', $result['lookup_mode']);
    }

    public function testClassifiesDelcoAndGmFamilies(): void
    {
        $classifier = new SerialClassifier();

        $cdr2005 = $classifier->classify('GM0205V1234567');
        self::assertSame('delco_gm', $cdr2005['family']);
        self::assertSame('exact', $cdr2005['lookup_mode']);

        $cdr500 = $classifier->classify('GM1500X9475126');
        self::assertSame('delco_gm', $cdr500['family']);
        self::assertSame('exact', $cdr500['lookup_mode']);

        $grundigGm = $classifier->classify('GM0200T3378004');
        self::assertSame('grundig_opel_gm', $grundigGm['family']);
        self::assertSame('exact', $grundigGm['lookup_mode']);

        $ambiguous = $classifier->classify('GM0804A1234567');
        self::assertSame('gm_pending', $ambiguous['family']);
        self::assertSame('exact_pending', $ambiguous['lookup_mode']);
    }

    public function testClassifiesPhilipsLegacyFamilies(): void
    {
        $classifier = new SerialClassifier();

        $ph = $classifier->classify('PH7850W1386751');
        self::assertSame('philips_legacy', $ph['family']);
        self::assertSame('exact', $ph['lookup_mode']);

        $rn = $classifier->classify('RN593FT4000729');
        self::assertSame('philips_legacy', $rn['family']);
        self::assertSame('exact', $rn['lookup_mode']);

        $mi = $classifier->classify('MI610SN9822316');
        self::assertSame('philips_legacy', $mi['family']);
        self::assertSame('exact', $mi['lookup_mode']);

        $pha = $classifier->classify('PHA12345678901');
        self::assertSame('philips_legacy', $pha['family']);
        self::assertSame('exact_pending', $pha['lookup_mode']);
    }

    public function testClassifiesGrundigLegacyFamilies(): void
    {
        $classifier = new SerialClassifier();

        $db = $classifier->classify('DB123456789012');
        self::assertSame('grundig_legacy', $db['family']);
        self::assertSame('exact', $db['lookup_mode']);

        $se = $classifier->classify('SE312345678901');
        self::assertSame('grundig_legacy', $se['family']);
        self::assertSame('exact', $se['lookup_mode']);

        $gr = $classifier->classify('GR1028V0110');
        self::assertSame('grundig_legacy', $gr['family']);
        self::assertSame('exact_pending', $gr['lookup_mode']);
    }

    public function testKeepsVagPriorityForSezAndSkz(): void
    {
        $classifier = new SerialClassifier();

        $sez = $classifier->classify('SEZ1Z2E5256507');
        self::assertSame('vag', $sez['family']);

        $skz = $classifier->classify('SKZ1Z2E5256507');
        self::assertSame('vag', $skz['family']);
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
