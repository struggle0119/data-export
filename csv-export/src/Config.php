<?php

namespace CsvExport;


class Config
{
    // 数据来源是数组
    const SOURCE_ARRAY = 1;

    // 数据来源是数据库查询
    const SOURCE_DATABASE = 2;

    /**
     * 支持的数据来源
     *
     * @return array
     */
    public final static function supportSource()
    {
        return [self::SOURCE_ARRAY, self::SOURCE_DATABASE];
    }
}