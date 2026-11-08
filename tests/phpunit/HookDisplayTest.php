<?php

require_once __DIR__ . '/HarnessTestCase.php';
require_once __DIR__ . '/ChildProcess.php';

class DashboardProbe extends statsbestmanufacturers
{
    public static $lastEngineParams = null;

    public function engine($params)
    {
        self::$lastEngineParams = $params;

        return 'GRID_ENGINE_OUTPUT';
    }

    protected function csvExport($datas)
    {
        throw new RuntimeException('csvExport must not run in dashboard probe.');
    }
}

class HookDisplayTest extends HarnessTestCase
{
    private static $dashboardUri =
        '/admin/index.php?controller=AdminStats&module=statsbestmanufacturers&tag=<b>x</b>&q="y"';

    protected function setUp()
    {
        parent::setUp();
        DashboardProbe::$lastEngineParams = null;
        $_SERVER['REQUEST_URI'] = self::$dashboardUri;
    }

    public function testDashboardMarkupAndEnginePayload()
    {
        $module = new DashboardProbe();
        $html = $module->hookDisplayAdminStatsModules(array());

        $this->assertContains('panel-heading', $html);
        $this->assertContains('Best brands', $html);
        $this->assertContains('GRID_ENGINE_OUTPUT', $html);
        $this->assertContains('class="btn btn-default export-csv"', $html);
        $this->assertContains('icon-cloud-upload', $html);
        $this->assertContains('CSV Export', $html);
        $this->assertContains(
            '/admin/index.php?controller=AdminStats&amp;module=statsbestmanufacturers'
            . '&amp;tag=x&amp;q=&quot;y&quot;&amp;export=1',
            $html
        );

        $payload = DashboardProbe::$lastEngineParams;
        $this->assertInternalType('array', $payload);
        $this->assertSame('Best brands', $payload['title']);
        $this->assertSame('sales', $payload['defaultSortColumn']);
        $this->assertSame('DESC', $payload['defaultSortDirection']);
        $this->assertSame('Displaying {0} - {1} of {2}', $payload['pagingMessage']);
        $payloadStrings = array();
        foreach ($payload as $value) {
            if (is_string($value)) {
                $payloadStrings[] = $value;
            }
        }
        $this->assertContains('Empty recordset returned.', $payloadStrings);

        $this->assertCount(3, $payload['columns']);
        $this->assertSame(
            array(
                array(
                    'id' => 'name',
                    'header' => 'Name',
                    'dataIndex' => 'name',
                    'align' => 'center',
                    'width' => 200,
                ),
                array(
                    'id' => 'quantity',
                    'header' => 'Quantity sold',
                    'dataIndex' => 'quantity',
                    'width' => 60,
                    'align' => 'center',
                ),
                array(
                    'id' => 'sales',
                    'header' => 'Total paid',
                    'dataIndex' => 'sales',
                    'width' => 60,
                    'align' => 'center',
                ),
            ),
            $payload['columns']
        );
    }

  public function testExportFlagRunsCsvExportInChildAndSkipsMarkup()
    {
        $result = $this->runHookChild(array('export' => '1'), false);
        $this->assertSame(0, $result['exitCode']);
        $this->assertSame(
            "CSV_SORT=sales\nCSV_DIR=DESC\nCSV_TITLE=Best brands\n"
            . "CSV_PAGE=Displaying {0} - {1} of {2}\nCSV_COLS=name,quantity,sales\n",
            $result['stdout']
        );
        $this->assertNotContains('panel-heading', $result['stdout']);
    }

    public function testExportZeroRendersDashboard()
    {
        $_GET['export'] = '0';
        $module = new DashboardProbe();
        $html = $module->hookDisplayAdminStatsModules(array());
        $this->assertContains('GRID_ENGINE_OUTPUT', $html);
        $this->assertNotNull(DashboardProbe::$lastEngineParams);
    }

    public function testExportTwoRendersDashboard()
    {
        $_GET['export'] = '2';
        $module = new DashboardProbe();
        $html = $module->hookDisplayAdminStatsModules(array());
        $this->assertContains('GRID_ENGINE_OUTPUT', $html);
        $this->assertNotNull(DashboardProbe::$lastEngineParams);
    }

    public function testPostZeroOverridesGetOne()
    {
        $_GET['export'] = '1';
        $_POST['export'] = '0';
        $module = new DashboardProbe();
        $html = $module->hookDisplayAdminStatsModules(array());
        $this->assertContains('GRID_ENGINE_OUTPUT', $html);
        $this->assertNotNull(DashboardProbe::$lastEngineParams);
    }

    public function testPostExportRunsCsvExportInChild()
    {
        $result = $this->runHookChild(array(), true);
        $this->assertSame(0, $result['exitCode']);
        $this->assertContains('CSV_SORT=sales', $result['stdout']);
    }

    private function runHookChild(array $get, $postOnly)
    {
        $bootstrap = __DIR__ . '/bootstrap.php';
        $exportProbe = __DIR__ . '/ExportProbe.php';
        $uri = self::$dashboardUri;
        $script = '<?php' . "\n"
            . '$_GET = ' . var_export($get, true) . ';' . "\n"
            . '$_POST = ' . ($postOnly ? "array('export' => '1')" : 'array()') . ';' . "\n"
            . '$_SERVER[\'REQUEST_URI\'] = ' . var_export($uri, true) . ';' . "\n"
            . 'require ' . var_export($bootstrap, true) . ';' . "\n"
            . 'require ' . var_export($exportProbe, true) . ';' . "\n"
            . '$module = new ExportProbe();' . "\n"
            . '$module->hookDisplayAdminStatsModules(array());' . "\n"
            . "echo 'UNREACHABLE';" . "\n";

        return ChildProcess::runPhpScript($script, $this->dbEnvFromProcess());
    }

    private function dbEnvFromProcess()
    {
        $env = array();
        foreach (array(
            'STATS_DB_HOST',
            'STATS_DB_PORT',
            'STATS_DB_NAME',
            'STATS_DB_USER',
            'STATS_DB_PASSWORD',
            'PHPUNIT_PHAR',
        ) as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $env[$key] = $value;
            }
        }

        return $env;
    }
}
