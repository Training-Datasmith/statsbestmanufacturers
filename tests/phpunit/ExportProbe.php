<?php

class ExportProbe extends statsbestmanufacturers
{
    public function engine($params)
    {
        throw new RuntimeException('engine must not run in export probe.');
    }

    protected function csvExport($datas)
    {
        echo 'CSV_SORT=' . $datas['defaultSortColumn'] . "\n";
        echo 'CSV_DIR=' . $datas['defaultSortDirection'] . "\n";
        echo 'CSV_TITLE=' . $datas['title'] . "\n";
        echo 'CSV_PAGE=' . $datas['pagingMessage'] . "\n";
        $cols = array();
        foreach ($datas['columns'] as $column) {
            $cols[] = $column['dataIndex'];
        }
        echo 'CSV_COLS=' . implode(',', $cols) . "\n";
        exit(0);
    }
}
