<?php

require_once __DIR__ . '/HarnessTestCase.php';

class ModuleMetadataTest extends HarnessTestCase
{
    public function testPublicProperties()
    {
        $module = new statsbestmanufacturers();
        $this->assertSame('statsbestmanufacturers', $module->name);
        $this->assertSame('administration', $module->tab);
        $this->assertSame('2.0.4', $module->version);
        $this->assertSame('PrestaShop', $module->author);
        $this->assertSame(0, $module->need_instance);
    }

    public function testVersionAndNameMatchConfigXml()
    {
        $module = new statsbestmanufacturers();
        $xml = simplexml_load_file(dirname(dirname(__DIR__)) . '/config.xml');
        $this->assertNotFalse($xml);
        $this->assertSame((string) $module->name, (string) $xml->name);
        $this->assertSame((string) $module->version, (string) $xml->version);
    }

    public function testVersionsCompliancy()
    {
        $module = new statsbestmanufacturers();
        $this->assertSame('1.7.1.0', $module->ps_versions_compliancy['min']);
        $this->assertSame(_PS_VERSION_, $module->ps_versions_compliancy['max']);
    }

    public function testTranslatedDisplayStrings()
    {
        $module = new statsbestmanufacturers();
        $this->assertSame('Best brands', $module->displayName);
        $this->assertSame(
            'Enrich your stats: add a list of the best-selling brands to your dashboard.',
            $module->description
        );
    }
}
