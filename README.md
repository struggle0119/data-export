# Data export
    1,Support array to csv file
    2,Support sql to csv file
    Please use sql mode export if you have large volume of data
    
# Return a zip file
    Local path will be return

# Usage
(new Export())->setPath('Your server local path')
        ->setLimit(10000) // Per file limit
        ->setSource(2) // 1 array , 2 sql
        ->setTitle([
            'author' => '作者',
            'date'   => '日期'
        ])
        ->dataToFile('SELECT author, date, content, title FROM table', [
            'host'     => '192.168.11.11',
            'port'     => '3306',
            'dbname'   => 'blog',
            'username' => 'root',
            'password' => '12345678'
        ]);