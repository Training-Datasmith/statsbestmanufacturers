<?php

require_once __DIR__ . '/HarnessTestCase.php';
require_once __DIR__ . '/SalesProbe.php';

class SalesQueryTest extends HarnessTestCase
{
    protected function setUp()
    {
        parent::setUp();
        Db::ensureSchema();
        $db = Db::getInstance();
        $db->truncateSalesFixture();
        $db->seedSalesFixture();
    }

    private function probe()
    {
        $probe = new SalesProbe();
        $probe->configureMarch2016Window();

        return $probe;
    }

    public function testTotalCountExcludesOutOfScopeRows()
    {
        $probe = $this->probe();
        $this->assertSame('2', (string) $probe->getTotalCount());
    }

    public function testAggregatesQuantityAndDividesByConversionRate()
    {
        $probe = $this->probe();
        $probe->setSort('name');
        $probe->setDirection('ASC');
        $probe->getData();

        $this->assertSame('2', (string) $probe->totalCount());
        $byName = array();
        foreach ($probe->values() as $row) {
            $byName[$row['name']] = $row;
        }
        $this->assertCount(2, $byName);
        $this->assertTrue(
            is_string($byName['Alpha']['quantity']) || is_int($byName['Alpha']['quantity'])
        );
        $this->assertSame('4', (string) $byName['Alpha']['quantity']);
        $this->assertSame('20.00', $byName['Alpha']['sales']);
        $this->assertSame('10', (string) $byName['Beta']['quantity']);
        $this->assertSame('15.00', $byName['Beta']['sales']);
    }

    public function testOrderBySalesDesc()
    {
        $probe = $this->probe();
        $probe->setSort('sales');
        $probe->setDirection('DESC');
        $probe->getData();
        $names = array();
        foreach ($probe->values() as $row) {
            $names[] = $row['name'];
        }
        $this->assertSame(array('Alpha', 'Beta'), $names);
    }

    public function testOrderByQuantityAsc()
    {
        $probe = $this->probe();
        $probe->setSort('quantity');
        $probe->setDirection('ASC');
        $probe->getData();
        $names = array();
        foreach ($probe->values() as $row) {
            $names[] = $row['name'];
        }
        $this->assertSame(array('Alpha', 'Beta'), $names);
    }

    public function testOrderByNameDesc()
    {
        $probe = $this->probe();
        $probe->setSort('name');
        $probe->setDirection('DESC');
        $probe->getData();
        $names = array();
        foreach ($probe->values() as $row) {
            $names[] = $row['name'];
        }
        $this->assertSame(array('Beta', 'Alpha'), $names);
    }

    public function testRejectedSortDoesNotReferenceUnknownColumn()
    {
        $probe = $this->probe();
        $probe->setSort('sales1');
        $probe->setDirection('DESC');
        $probe->getData();
        $this->assertCount(2, $probe->values());
    }

    public function testRejectedDirectionSortsAscending()
    {
        $probe = $this->probe();
        $probe->setSort('sales');
        $probe->setDirection('desc');
        $probe->getData();
        $names = array();
        foreach ($probe->values() as $row) {
            $names[] = $row['name'];
        }
        $this->assertSame(array('Beta', 'Alpha'), $names);
    }

    public function testMissingDirectionSortsAscending()
    {
        $probe = $this->probe();
        $probe->setSort('sales');
        $probe->setDirection(null);
        $probe->getData();
        $names = array();
        foreach ($probe->values() as $row) {
            $names[] = $row['name'];
        }
        $this->assertSame(array('Beta', 'Alpha'), $names);
    }

    public function testLimitOffsetAndTotalCount()
    {
        $probe = $this->probe();
        $probe->setSort('name');
        $probe->setDirection('ASC');

        $probe->setWindow(0, 1);
        $probe->getData();
        $this->assertCount(1, $probe->values());
        $this->assertSame('Alpha', $probe->values()[0]['name']);
        $this->assertSame('2', (string) $probe->totalCount());

        $probe->setWindow(1, 1);
        $probe->getData();
        $this->assertCount(1, $probe->values());
        $this->assertSame('Beta', $probe->values()[0]['name']);
        $this->assertSame('2', (string) $probe->totalCount());

        $probe->setWindow(0, 0);
        $probe->getData();
        $this->assertInternalType('array', $probe->values());
        $this->assertCount(0, $probe->values());
        $this->assertSame('2', (string) $probe->totalCount());

        $probe->setWindow('0', '1');
        $probe->getData();
        $this->assertCount(1, $probe->values());
        $this->assertSame('Alpha', $probe->values()[0]['name']);

        $probe->setWindow(5, 10);
        $probe->getData();
        $this->assertInternalType('array', $probe->values());
        $this->assertCount(0, $probe->values());
        $this->assertSame('2', (string) $probe->totalCount());
    }

    public function testNonIntegerLimitAndNegativeStartDoNotRestrict()
    {
        $probe = $this->probe();
        $probe->setSort('name');
        $probe->setDirection('ASC');

        $probe->setWindow(0, '1abc');
        $probe->getData();
        $this->assertCount(2, $probe->values());

        $probe->setWindow(-1, 10);
        $probe->getData();
        $this->assertCount(2, $probe->values());

        $probe->setWindow(1.5, 10);
        $probe->getData();
        $this->assertCount(2, $probe->values());
    }
}
