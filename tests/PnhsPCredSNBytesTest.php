<?php

declare(strict_types=1);

namespace NFePHP\NFe\Tests;

use NFePHP\NFe\Make;
use stdClass;

/**
 * Divergencia deliberada e temporaria do upstream (fork PNHS): pCredSN do CSOSN 101
 * volta a 2 casas, como na v5.2.5, para nao mudar os bytes das notas do Simples.
 * CSOSN 201 e 900 ja eram 4 casas na v5.2.5 e continuam assim.
 */
class PnhsPCredSNBytesTest extends NFeTestCase
{
    /**
     * @dataProvider casosProvider
     */
    public function test_pCredSN_formatacao_igual_a_v525(string $csosn, string $esperado): void
    {
        $make = new Make('PL_010_V1');
        $make->tagide(new stdClass());
        $emit = new stdClass();
        $emit->CRT = 1;
        $make->tagemit($emit);

        $std = new stdClass();
        $std->item = 1;
        $std->orig = 0;
        $std->CSOSN = $csosn;
        $std->pCredSN = 2.56;
        $std->vCredICMSSN = 2.56;
        if ($csosn !== '101') {
            $std->modBCST = 4;
            $std->vBCST = 100;
            $std->pICMSST = 18;
            $std->vICMSST = 18;
            $std->modBC = 3;
            $std->vBC = 100;
            $std->pICMS = 18;
            $std->vICMS = 18;
        }
        $tag = $make->tagICMSSN($std);
        $this->assertSame($esperado, $tag->getElementsByTagName('pCredSN')->item(0)->nodeValue);
    }

    public static function casosProvider(): array
    {
        return [
            'CSOSN 101 (2 casas, como v5.2.5)' => ['101', '2.56'],
            'CSOSN 201 (4 casas, inalterado)' => ['201', '2.5600'],
            'CSOSN 900 (4 casas, inalterado)' => ['900', '2.5600'],
        ];
    }
}
