<?php

declare(strict_types=1);

namespace NFePHP\NFe\Tests;

use NFePHP\Common\Certificate;
use NFePHP\NFe\Make;
use NFePHP\NFe\Tests\Common\ToolsFake;
use stdClass;

/**
 * NT 2026.005 v1.00 - IBS e CBS no Simples Nacional
 * tpRegimeIBSCBS (C21a), cAtivSN (I17d), gTribSN do item (UB115a) e do total (W59h)
 */
class SimplesNacionalIBSCBSTest extends NFeTestCase
{
    public function test_tagEmit_tpRegimeIBSCBS_apos_CRT(): void
    {
        $make = new Make('PL_010_V1');
        $this->buildNFe55SimplesNacional($make, true);

        $dom = $this->render($make);
        $this->assertSame(
            ['CNPJ', 'xNome', 'xFant', 'enderEmit', 'IE', 'CRT', 'tpRegimeIBSCBS'],
            $this->childNames($dom->getElementsByTagName('emit')->item(0))
        );
        $this->assertSame('1', $dom->getElementsByTagName('tpRegimeIBSCBS')->item(0)->nodeValue);
    }

    public function test_tagEmit_sem_tpRegimeIBSCBS_nao_gera_a_tag(): void
    {
        $make = new Make('PL_010_V1');
        $this->buildNFe55SimplesNacional($make, false);

        $dom = $this->render($make);
        $this->assertSame(0, $dom->getElementsByTagName('tpRegimeIBSCBS')->length);
    }

    public function test_tagprod_cAtivSN_antes_da_DI(): void
    {
        $make = new Make('PL_010_V1');
        $this->buildNFe55SimplesNacional($make, false);

        $std = new stdClass();
        $std->item = 1;
        $std->nDI = '123456';
        $std->dDI = '2026-10-01';
        $std->xLocDesemb = 'SANTOS';
        $std->UFDesemb = 'SP';
        $std->dDesemb = '2026-10-01';
        $std->tpViaTransp = 1;
        $std->vAFRMM = 0;
        $std->tpIntermedio = 1;
        $std->cExportador = '123';
        $make->tagDI($std);

        $std = new stdClass();
        $std->item = 1;
        $std->nDI = '123456';
        $std->nAdicao = 1;
        $std->nSeqAdic = 1;
        $std->cFabricante = 'FAB';
        $make->tagadi($std);

        $dom = $this->render($make);
        $nomes = $this->childNames($dom->getElementsByTagName('prod')->item(0));
        $this->assertSame(
            array_search('cAtivSN', $nomes, true) + 1,
            array_search('DI', $nomes, true)
        );
        $this->assertSame('01', $dom->getElementsByTagName('cAtivSN')->item(0)->nodeValue);
    }

    public function test_taggTribSN_no_item_e_no_total(): void
    {
        $make = new Make('PL_010_V1');
        $this->buildNFe55SimplesNacional($make, true);

        $dom = $this->render($make);

        $ibscbs = $dom->getElementsByTagName('IBSCBS')->item(0);
        $this->assertSame(['CST', 'cClassTrib', 'gTribSN'], $this->childNames($ibscbs));
        $this->assertSame('0.1000', $dom->getElementsByTagName('pIBSSN')->item(0)->nodeValue);
        $this->assertSame('0.10', $ibscbs->getElementsByTagName('vIBSSN')->item(0)->nodeValue);
        $this->assertSame('0.9000', $dom->getElementsByTagName('pCBSSN')->item(0)->nodeValue);
        $this->assertSame('0.90', $ibscbs->getElementsByTagName('vCBSSN')->item(0)->nodeValue);

        $total = $dom->getElementsByTagName('IBSCBSTot')->item(0);
        $gTribSN = $total->getElementsByTagName('gTribSN')->item(0);
        $this->assertSame(['vIBSSN', 'vCBSSN'], $this->childNames($gTribSN));
        $this->assertSame('0.10', $gTribSN->getElementsByTagName('vIBSSN')->item(0)->nodeValue);
        $this->assertSame('0.90', $gTribSN->getElementsByTagName('vCBSSN')->item(0)->nodeValue);
    }

    public function test_taggTribSN_sem_pIBSSN_gera_so_gCBSSN(): void
    {
        $make = new Make('PL_010_V1');

        $std = new stdClass();
        $std->item = 1;
        $std->pCBSSN = 0.9;
        $std->vCBSSN = 0.9;
        $gTribSN = $make->taggTribSN($std);

        $this->assertSame(['gCBSSN'], $this->childNames($gTribSN));
    }

    public function test_IBSCBSTot_sem_gTribSN_nao_gera_o_grupo(): void
    {
        $make = new Make('PL_010_V1');
        $total = $make->tagIBSCBSTot(new stdClass());

        $this->assertSame(0, $total->getElementsByTagName('gTribSN')->length);
    }

    public function test_nfe_simples_nacional_valida_no_schema_PL_010_V1(): void
    {
        $make = new Make('PL_010_V1');
        $this->buildNFe55SimplesNacional($make, true);
        $xml = $make->getXML();
        $this->assertSame([], $make->getErrors());

        $signed = $this->getTools()->signNFe($xml);

        $dom = new \DOMDocument();
        $dom->loadXML($signed);
        $this->assertSame(1, $dom->getElementsByTagName('Signature')->length);
        $this->assertSame(1, $dom->getElementsByTagName('cAtivSN')->length);
        $this->assertSame(2, $dom->getElementsByTagName('gTribSN')->length);
    }

    /**
     * NT 2026.005 (W59h) x NT 2025.002 v1.52 (W59e-W59g): no total, gTribSN vem DEPOIS de gEstornoCred.
     */
    public function test_IBSCBSTot_gTribSN_vem_depois_de_gEstornoCred(): void
    {
        $make = new Make('PL_010_V1');
        $this->buildNFe55SimplesNacional($make, true);
        $this->addEstorno($make);

        $dom = $this->render($make);
        $total = $dom->getElementsByTagName('IBSCBSTot')->item(0);
        $nomes = $this->childNames($total);
        $this->assertSame(['gEstornoCred', 'gTribSN'], array_slice($nomes, -2));

        // no item a ordem e a inversa (UB115a entre UB115 e UB116): gTribSN antes de gEstornoCred
        $item = $dom->getElementsByTagName('IBSCBS')->item(0);
        $this->assertSame(['CST', 'cClassTrib', 'gTribSN', 'gEstornoCred'], $this->childNames($item));
    }

    /**
     * @dataProvider schemasProvider
     */
    public function test_nfe_com_gEstornoCred_e_gTribSN_no_total_valida_no_xsd(string $schemes): void
    {
        $make = new Make($schemes);
        $this->buildNFe55SimplesNacional($make, true);
        $this->addEstorno($make);
        $xml = $make->getXML();
        $this->assertSame([], $make->getErrors());

        $this->assertTrue($this->validaXsd($xml, $schemes));
    }

    /**
     * @dataProvider schemasProvider
     */
    public function test_xsd_rejeita_gTribSN_antes_de_gEstornoCred_no_total(string $schemes): void
    {
        $make = new Make($schemes);
        $this->buildNFe55SimplesNacional($make, true);
        $this->addEstorno($make);

        $dom = $this->render($make);
        $total = $dom->getElementsByTagName('IBSCBSTot')->item(0);
        $estorno = $total->getElementsByTagName('gEstornoCred')->item(0);
        $gTribSN = $total->getElementsByTagName('gTribSN')->item(0);
        $total->insertBefore($gTribSN, $estorno); // ordem errada (a da PR original)

        $this->expectException(\Throwable::class);
        $this->validaXsd($dom->saveXML(), $schemes);
    }

    /**
     * @dataProvider schemasProvider
     */
    public function test_nfe_simples_nacional_sem_estorno_valida_no_xsd(string $schemes): void
    {
        $make = new Make($schemes);
        $this->buildNFe55SimplesNacional($make, true);
        $this->assertTrue($this->validaXsd($make->getXML(), $schemes));
    }

    public static function schemasProvider(): array
    {
        return [
            'PL_010_V1' => ['PL_010_V1'],
            'PL_010_V1.30' => ['PL_010_V1.30'],
        ];
    }

    private function addEstorno(Make $make): void
    {
        $std = new stdClass();
        $std->item = 1;
        $std->vIBSEstCred = 0.01;
        $std->vCBSEstCred = 0.05;
        $make->taggEstornoCred($std);
    }

    private function validaXsd(string $xml, string $schemes): bool
    {
        preg_match('/<infNFe[^>]*\bId="([^"]+)"/', $xml, $m);
        $b64 = base64_encode(str_repeat('P', 20));
        $sig = '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo>'
            . '<CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>'
            . '<SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"/>'
            . '<Reference URI="#' . $m[1] . '"><Transforms>'
            . '<Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>'
            . '<Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/></Transforms>'
            . '<DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/><DigestValue>' . $b64
            . '</DigestValue></Reference></SignedInfo><SignatureValue>' . $b64 . '</SignatureValue>'
            . '<KeyInfo><X509Data><X509Certificate>' . $b64 . '</X509Certificate></X509Data></KeyInfo></Signature>';
        $xml = str_replace('</NFe>', $sig . '</NFe>', $xml);
        return \NFePHP\Common\Validator::isValid($xml, dirname(__DIR__) . "/schemes/$schemes/nfe_v4.00.xsd");
    }

    private function render(Make $make): \DOMDocument
    {
        $dom = new \DOMDocument();
        $dom->loadXML($make->getXML());
        return $dom;
    }

    private function childNames(\DOMElement $node): array
    {
        $nomes = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $nomes[] = $child->nodeName;
            }
        }
        return $nomes;
    }

    private function getTools(): ToolsFake
    {
        $config = json_decode($this->configJson, true);
        $config['schemes'] = 'PL_010_V1';
        $tools = new ToolsFake(
            json_encode($config),
            Certificate::readPfx($this->contentpfx, $this->passwordpfx)
        );
        $tools->model(55);
        return $tools;
    }

    private function buildNFe55SimplesNacional(Make $make, bool $hibrido): void
    {
        $std = new stdClass();
        $std->Id = '35261158716523000119550010000000301800700081';
        $std->versao = '4.00';
        $make->taginfNFe($std);

        $std = new stdClass();
        $std->cUF = '35';
        $std->cNF = '80070008';
        $std->natOp = 'VENDA';
        $std->mod = '55';
        $std->serie = '1';
        $std->nNF = '30';
        $std->dhEmi = '2026-11-16T11:30:00-03:00';
        $std->dhSaiEnt = '2026-11-16T11:30:00-03:00';
        $std->tpNF = '1';
        $std->idDest = '1';
        $std->cMunFG = '3550308';
        $std->tpImp = '1';
        $std->tpEmis = '1';
        $std->cDV = '0';
        $std->tpAmb = '2';
        $std->finNFe = '1';
        $std->indFinal = '0';
        $std->indPres = '1';
        $std->procEmi = '0';
        $std->verProc = '5.0';
        $make->tagide($std);

        $std = new stdClass();
        $std->xNome = 'EMPRESA TESTE LTDA';
        $std->xFant = 'EMPRESA TESTE';
        $std->IE = '123456789012';
        $std->CRT = '1';
        $std->tpRegimeIBSCBS = $hibrido ? '1' : null;
        $std->CNPJ = '58716523000119';
        $make->tagEmit($std);

        $std = new stdClass();
        $std->xLgr = 'Rua Teste';
        $std->nro = '100';
        $std->xBairro = 'Centro';
        $std->cMun = '3550308';
        $std->xMun = 'Sao Paulo';
        $std->UF = 'SP';
        $std->CEP = '01001000';
        $std->cPais = '1058';
        $std->xPais = 'BRASIL';
        $make->tagenderEmit($std);

        $std = new stdClass();
        $std->xNome = 'CLIENTE TESTE';
        $std->indIEDest = '9';
        $std->CNPJ = '11222333000181';
        $make->tagdest($std);

        $std = new stdClass();
        $std->item = 1;
        $std->cProd = '001';
        $std->cEAN = 'SEM GTIN';
        $std->xProd = 'Produto Teste';
        $std->NCM = '61091000';
        $std->CFOP = '5102';
        $std->uCom = 'UN';
        $std->qCom = '10.0000';
        $std->vUnCom = '10.0000000000';
        $std->vProd = '100.00';
        $std->cEANTrib = 'SEM GTIN';
        $std->uTrib = 'UN';
        $std->qTrib = '10.0000';
        $std->vUnTrib = '10.0000000000';
        $std->indTot = 1;
        $std->cAtivSN = '01';
        $make->tagprod($std);

        $std = new stdClass();
        $std->item = 1;
        $std->orig = '0';
        $std->CSOSN = '102';
        $make->tagICMSSN($std);

        $std = new stdClass();
        $std->item = 1;
        $std->CST = '49';
        $std->vBC = 0;
        $std->pPIS = 0;
        $std->vPIS = 0;
        $make->tagPIS($std);

        $std = new stdClass();
        $std->item = 1;
        $std->CST = '49';
        $std->vBC = 0;
        $std->pCOFINS = 0;
        $std->vCOFINS = 0;
        $make->tagCOFINS($std);

        $std = new stdClass();
        $std->item = 1;
        $std->CST = '000';
        $std->cClassTrib = '000001';
        $make->tagIBSCBS($std);

        $std = new stdClass();
        $std->item = 1;
        $std->pIBSSN = 0.1;
        $std->vIBSSN = 0.1;
        $std->pCBSSN = 0.9;
        $std->vCBSSN = 0.9;
        $make->taggTribSN($std);

        $std = new stdClass();
        $std->modFrete = '9';
        $make->tagtransp($std);

        $std = new stdClass();
        $std->vTroco = null;
        $make->tagpag($std);

        $std = new stdClass();
        $std->tPag = '01';
        $std->vPag = '100.00';
        $make->tagdetPag($std);
    }
}
