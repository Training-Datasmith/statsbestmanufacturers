<?php

class SalesProbe extends statsbestmanufacturers
{
    public function setSort($sort)
    {
        $this->_sort = $sort;
    }

    public function setDirection($direction)
    {
        $this->_direction = $direction;
    }

    public function setWindow($start, $limit)
    {
        $this->_start = $start;
        $this->_limit = $limit;
    }

    public function configureMarch2016Window()
    {
        $this->setStatsDateRange('2016-03-01', '2016-03-31');
    }

    public function values()
    {
        return $this->_values;
    }

    public function totalCount()
    {
        return $this->_totalCount;
    }
}
