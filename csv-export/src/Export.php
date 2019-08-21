<?php

namespace CsvExport;

use \ZipArchive;


class Export
{
    // 数据来源 模式是数组
    protected $source   = Config::SOURCE_ARRAY;

    // 文件保存路径
    protected $path     = null;

    // 设置表头
    protected $title    = null;

    // csv文件行数限制
    protected $limit    = 1000000;

    // 文件列表
    protected $files    = [];

    // csv文件换行符
    const SEPARATOR     = "\r\n";

    /**
     * 数据写入文件
     *
     * @param null $input
     * @param $options
     [
        'host' => '192.168.11.11',
        'port' => '3306',
        'dbname' => 'blog',
        'username' => 'root',
        'password' => 'GuoJinLi!1'
     ]
     * @return string
     * @throws ExportException
     */
    public function dataToFile($input = null, $options = [])
    {
        try {
            // 参数校验
            $this->verify();

            if ($this->source == Config::SOURCE_ARRAY) {
                $this->arrayToFile($input);
            } else if ($this->source == Config::SOURCE_DATABASE) {
                $this->databaseToFile($input, $options);
            }
            $ret = $this->zip($this->files);
        } catch (\Exception $e) {
            throw new ExportException("文件导出处理失败" . $e->getMessage());
        }

        return $ret;
    }

    /**
     * 数组格式的数据写入文件
     *
     * @param array $data
     */
    private function arrayToFile($data = [])
    {
        // 文件名称索引前缀
        $fileIndex = 1;
        // 获取文件路径
        $this->files[] = $filePath = $this->getFilePath($fileIndex);
        // 写入模式打开文件
        $resource = fopen($filePath, 'w');
        // 写入内容
        if ($data) {
            $index = 1;
            foreach ($data as $key => $value) {
                // 第一条需要写入表头
                if ($index == 1) {
                    $this->writeTitle($resource, $value);
                }
                // 达到一个文件上线时，重新打开一个新文件继续写入
                if ($index > ($this->limit * $fileIndex)) {
                    fclose($resource);
                    $fileIndex++;
                    $this->files[] = $filePath = $this->getFilePath($fileIndex);
                    $resource = fopen($filePath, 'w');
                    $this->writeTitle($resource, $data[0]);
                }
                $this->writeBody($resource, $value);
                $index++;
            }
        }
        // 关闭句柄
        fclose($resource);
    }

    /**
     * 数据库读取数据直接写入数据库，防止数据量大的时候内存溢出
     * @param $sql
     * @param $options
     */
    private function databaseToFile($sql, $options)
    {
        $pdo = new \PDO("mysql:host={$options['host']};port={$options['port']};dbname={$options['dbname']};
        charset=utf8;", $options['username'], $options['password']);
        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $rows = $pdo->query($sql, \PDO::FETCH_ASSOC);
        // 文件名称索引前缀
        $fileIndex = 1;
        // 获取文件路径
        $this->files[] = $filePath = $this->getFilePath($fileIndex . '-');
        // 写入模式打开文件
        $resource = fopen($filePath, 'w');
        $index = 1;
        foreach ($rows as $row) {
            // 第一条需要写入表头
            if ($index == 1) {
                $this->writeTitle($resource, $row);
            }
            // 达到一个文件上线时，重新打开一个新文件继续写入
            if ($index > ($this->limit * $fileIndex)) {
                fclose($resource);
                $fileIndex++;
                $this->files[] = $filePath = $this->getFilePath($fileIndex . '-');
                $resource = fopen($filePath, 'w');
                $this->writeTitle($resource, $row);
            }
            $this->writeBody($resource, $row);
            $index++;
        }
        // 关闭句柄
        fclose($resource);
    }

    /**
     * 写入表头，获取数据的第一行的字段和表头字段对应
     *
     * @param $resource
     * @param $firstRow
     */
    private function writeTitle($resource, $firstRow)
    {
        $title = [];
        if ($firstRow) {
            foreach ($firstRow as $field => $row) {
                $title[$field] = isset($this->title[$field]) ? $this->title[$field] : '';
            }
        } else {
            $title = $this->title;
        }
        fwrite($resource, self::encode(implode(',', $title)) . self::SEPARATOR);
    }

    /**
     * 写入文件主体内容
     *
     * @param $resource
     * @param $value
     */
    private function writeBody($resource, $value)
    {
        array_walk($value, function (&$v) {
            $v = $this->filterValue($v);
        });
        fwrite($resource, self::encode(implode(',', $value)) . self::SEPARATOR);
    }

    /**
     * 对生成的下载文件进行打包
     *
     * @return string
     * @param $files
     * @throws ExportException
     */
    private function zip($files)
    {
        if (!class_exists('ZipArchive')) {
            throw new ExportException("尚未安装ZipArchive扩展");
        } else {
            $zip     = new ZipArchive();
            $zipName = $this->filename('zip');
            $zipFile = $this->path . DIRECTORY_SEPARATOR . $zipName;
            if ($zip->open($zipFile, ZIPARCHIVE::CREATE) !== true) {
                throw new ExportException("不能创建压缩包");
            }
            // 循环将每个报表文件添加到zip压缩文件包里
            foreach ($files as $file) {
                $bool = $zip->addFile($file, basename($file));
                if (!$bool) {
                    throw new ExportException("压缩包添加文件失败【" . $file . "】");
                }
            }
            $zip->close();
            if (!file_exists($zipFile)) {
                throw new ExportException("导出文件不存在");
            }
        }

        // 打包完成后删除文件
        foreach ($files as $file) {
            @unlink($file);
        }

        return $zipFile;
    }

    /**
     * 参数校验
     *
     * @throws ExportException
     */
    private function verify()
    {
        if (!in_array($this->source, Config::supportSource())) {
            throw new ExportException("不支持该数据来源");
        }

        if (!$this->path) {
            throw new ExportException("请设置文件保存的路径");
        }

        if (!$this->title) {
            throw new ExportException("请设置表头");
        }
    }

    /**
     * 获取文件文件路径
     *
     * @param $prefix
     * @return string
     */
    private function getFilePath($prefix = '')
    {
        // 检查路径
        $this->checkPath();

        return $this->path . DIRECTORY_SEPARATOR . $this->filename('csv', $prefix);
    }

    /**
     * 检查路径，不存在则创建
     */
    private function checkPath()
    {
        // 如果不存在则创建路径
        if (!file_exists($this->path)) {
            mkdir($this->path, 0777, true);
        }
    }

    /**
     * 生成文件名字
     *
     * @param string $prefix
     * @param int $length
     * @param $suffix
     * @return bool|string
     */
    private function filename($suffix = 'csv', $prefix = '', $length = 32)
    {
        $filename = date('YmdHis', time()) . time() . mt_rand(100000000, 999999999);
        return substr($prefix . $filename, 0, $length) . '.' . $suffix;
    }

    /**
     * 设置数据源
     *
     * @param $source
     * @return $this
     */
    public function setSource($source)
    {
        $this->source = $source;
        return $this;
    }

    /**
     * 设置文件保存路径
     *
     * @param $path
     * @return $this
     */
    public function setPath($path)
    {
        $this->path = $path;
        return $this;
    }

    /**
     * 设置表头
     * @param $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * 设置每个csv写入的条数
     *
     * @param $limit
     * @return $this
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * 对字符串转码
     * @param $string
     * @return string
     */
    public static function encode($string)
    {
        return iconv('UTF-8', 'GBK//IGNORE', $string);
    }

    /**
     * 数据处理 过滤html标签 & 过滤特殊字符 & 将每个数值都当做是字符串处理
     * @param $value
     * @return string
     */
    public function filterValue($value)
    {
        $value = strip_tags($value);
        $value = str_replace(["\r\n", "\r", "\n", "\""], ["", "", "", "\"\""], $value);
        return "\"\t" . $value . "\"";
    }
}