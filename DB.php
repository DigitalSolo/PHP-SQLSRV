<?php
const
    SQL_BINARY = -2,
    SQL_BIT = -7,
    SQL_CHAR = 1,

    SQL_TYPE_DATE = 91,
    SQL_TYPE_TIMESTAMP = 93,

    SQL_SS_TIMESTAMPOFFSET = -155,
    SQL_DECIMAL = 3,
    SQL_FLOAT = 6,
    SQL_LONGVARBINARY = -4,
    SQL_INTEGER = 4,
    SQL_WCHAR = -8,
    SQL_WLONGVARCHAR = -10,
    SQL_NUMERIC = 2,
    SQL_WVARCHAR = -9,
    SQL_REAL = 7,
    SQL_SMALLINT = 5,
    SQL_SS_VARIANT = -150,
    SQL_LONGVARCHAR = -1,
    SQL_SS_TIME2 = -154,
    SQL_TINYINT = -6,
    SQL_SS_UDT = -151,
    SQL_GUID = -11,
    SQL_VARBINARY = -3,
    SQL_VARCHAR = 12,
    SQL_SS_XML = -152;
const
    SQLHOST = '',
    SQLUID = '',
    SQLPW = '',
    SQLDB = '';
class DB
{
    private static $_instance = null;
    public array
    $_a_field_metadata = [],
    $_results = [],
    $_a_params = [];
    private
    $_SqlSrv,
    $_query,
    $_sql_error;
    private bool
    $_DeadLock = false,
    $_error = false,
    $_debug = false;
    private int $_retry_count = 0,
    $_count = 0;
    private ?string
    $_sql = null,
    $_query_type = null,
    $_insert_id = null,
    $_message = null,
    $_NOEXEC = null,
    $_old_sql = null;


    public function __construct()
    {
        try {

            $a["UID"] = SQLUID;
            $a["PWD"] = SQLPW;
            $a["Database"] = SQLDB;
            $a["LoginTimeout"] = 5;
            //$a["Trusted_Connection"] = true;
            //$a['Integrated Security'] = true;
            //$a['Asynchronous Processing']=True;

            //$a['ColumnEncryption'] = "Enabled";
            //$a['Encrypt'] = true;
            //$a['TrustServerCertificate'] = true;
            //$a["MultipleActiveResultSets"] = true;
            $a["TraceOn"] = 0;
            $a["CharacterSet"] = "UTF-8";
            $a["ConnectionPooling"] = 1;
            $this->_SqlSrv = sqlsrv_connect(SQLHOST, $a);
        } catch (Exception $e) {
            error_log(print_r($e, true));
            error_log(__FILE__ . " " . __FUNCTION__ . "\n LINE: " . __LINE__ . "\n" . print_r(sqlsrv_errors(), true));
            die(__FILE__ . " " . __FUNCTION__ . "\n LINE: " . __LINE__ . "\n" . print_r(sqlsrv_errors(), true));
        }
    }

    public static function getInstance(bool $force_new = false): object
    {
        if ($force_new) {
            self::$_instance = new DB();
            return self::$_instance;
        }
        if (!isset(self::$_instance)) {
            try {
                self::$_instance = new DB();
            } catch (Exception $e) {
                error_log(print_r($e, true));
                die(__FILE__ . " " . __FUNCTION__ . "\n LINE: " . __LINE__ . "\n" . print_r(sqlsrv_errors(), true));
            }
        }
        return self::$_instance;
    }

    public function delete(string $table, $where = [])
    {
        return $this->action('DELETE ', $table, $where);
    }

    public function action(string $action, string $table, $where = []): object
    {
        if (count($where) === 3) {
            $operators = ['=', '>', '<', '>=', '<=', 'between', 'like'];
            $field = $where[0];
            $operator = $where[1];
            $value = str_replace("\'", "%", $where[2]);
            if (in_array($operator, $operators)) {
                if ($operator == 'like') {
                    $sql = "{$action} FROM {$table} WHERE cast({$field} as varchar(max)) {$operator} '%{$value}%'";
                    if (!$this->query($sql)->error()) {
                        return $this;
                    }
                } else {
                    $sql = "{$action} FROM {$table} WHERE {$field} {$operator} ?";
                    if (!$this->query($sql, [$value])->error()) {
                        return $this;
                    }
                }
            }
            return $this;
        }
        return $this;
    }

    public function query(string $sql, $params = []): object
    {

        $this->_a_params = $params;
        $error_output = null;
        $this->_query = null;
        $this->_results = [];
        $this->_error = false;
        $this->_sql_error = null;
        $this->_count = 0;
        unset($this->_query);
        unset($this->_results);
        unset($this->_error);

        $this->_sql = $sql;
        if (strstr($this->_sql, 'SET NOEXEC ON')) {
            $this->_NOEXEC = true;
        }

        $this->_query_type = strtoupper(substr($this->_sql, 0, strpos($this->_sql, ' ')));
        if (!is_resource($this->_SqlSrv)) {
            $this->_error = true;
            echo '<br><br><h1 style="text-align: center;">Unable to connect to the database. This is a critical error.</h1>';
            echo $this->_message;
            die($this->_message);
        }
        $this->prepare_params();
        if ($this->_debug) {
            error_log("\nDebug DB Class\n\tSQL: " . $this->_sql . "\n\n\tQuery Type: " . $this->_query_type . "\n");
            error_log("\nBacktrace\n\t:" . print_r(debug_backtrace(), true));
        }

        set_time_limit(180);

        switch ($this->_query_type) {
            case 'EXEC':
            case 'EXECUTE':
                set_time_limit(600);
                $this->_query = sqlsrv_prepare($this->_SqlSrv, $this->_sql, $this->_a_params);
                if ($this->_query) {
                    if (!sqlsrv_execute($this->_query)) {
                        $this->error_handler();
                    }
                    $this->_count = 0;
                    while ($obj = sqlsrv_fetch_object($this->_query)) {
                        $this->_results[] = $obj;
                        $this->_count++;
                    }
                } else if (!$this->_query) {
                    $this->_error = true;
                    if (is_array($this->_a_params)) {
                        foreach ($this->_a_params as &$param) {
                            $param = substr($param, 0, 25);
                        }
                    }
                    $this->error_handler();
                } else {
                    $this->_count = 0;
                    while ($obj = sqlsrv_fetch_object($this->_query)) {
                        $this->_results[] = $obj;
                        $this->_count++;
                    }
                    $this->format_message(debug_backtrace());
                }
                break;
            case 'SELECT':
            case 'WITH': /*replace single quotes with % assumes this will always be for search purposed in a WHERE clause*/
                if ($this->_debug) {
                    $error_output .= "\n\tLine: " . __LINE__;
                }
                if ($this->_sql == $this->_old_sql) {
                    $this->_retry_count++;
                }
                $this->_old_sql = $this->_sql;

                if (substr_count($this->_sql, "?") == count($this->_a_params)) {
                    $this->_query = sqlsrv_prepare($this->_SqlSrv, $this->_sql, $this->_a_params, ["Scrollable" => SQLSRV_CURSOR_STATIC]);
                } else {
                    $this->_query = sqlsrv_prepare($this->_SqlSrv, $this->_sql, null, ["Scrollable" => SQLSRV_CURSOR_STATIC]);
                }
                set_time_limit(360);
                if ($this->_query) {
                    if (!sqlsrv_execute($this->_query)) {
                        $this->error_handler();
                    }
                } else {
                    $this->error_handler();
                }
                if (is_resource($this->_query)) {
                    $this->_count = sqlsrv_num_rows($this->_query);
                }

                if ($this->_debug) {
                    error_log("\tLine: " . __LINE__);
                }
                if (strtolower(substr($this->_sql, 0, 13)) == 'select count(') {
                    if ($this->_debug) {
                        error_log("\tLine: " . __LINE__);
                    }
                    if (is_resource($this->_query)) {
                        while ($obj = sqlsrv_fetch_object($this->_query)) {
                            $this->_results[] = $obj;
                            $a = (array) $obj;
                            $this->_count = reset($a);
                        }
                    }
                } else {
                    if ($this->_debug) {
                        $error_output .= "\tLine: " . __LINE__;
                    }
                    try {
                        if (is_resource($this->_query)) {
                            if ($this->_debug) {
                                $error_output .= "\tObject: " . print_r(sqlsrv_fetch_object($this->_query), true);
                            }
                            if ($this->_count == 1) {
                                $this->_results[] = sqlsrv_fetch_object($this->_query);

                            } else {
                                while ($obj = sqlsrv_fetch_object($this->_query)) {
                                    set_time_limit(60);
                                    $this->_results[] = $obj;
                                }
                            }
                        } else {
                            $this->error_handler();
                        }

                    } catch (Exception $e) {
                        $error_output .= "###Could not get count of records###";
                    }
                }
                if ($this->_debug) {
                    $error_output .= "\tLine: " . __LINE__;
                }
                if ($this->_debug) {
                    $error_output .= "\tResults: " . print_r($this->_results, true);
                }

                break;
            default: //Insert Update
                $field_values = array_values($this->_a_params);
                if ($this->_query = sqlsrv_prepare($this->_SqlSrv, $this->_sql, $field_values, ["Scrollable" => SQLSRV_CURSOR_FORWARD])) {
                    if ($this->_query) {
                        if (!sqlsrv_execute($this->_query)) {
                            $this->error_handler();
                        }
                    }
                    if (sqlsrv_next_result($this->_query)) {
                        sqlsrv_fetch($this->_query);
                        try {
                            if (sqlsrv_has_rows($this->_query)) {
                                $this->_insert_id = sqlsrv_get_field($this->_query, 0);
                            }
                        } catch (Exception $e) {
                            $this->error_handler();
                        }
                    }
                } else {
                    $this->error_handler();
                }
                break;
        }
        if ($this->_debug) {
            $error_output .= "\n\tLine: " . __LINE__;
        }
        if ($this->_query) {
            sqlsrv_free_stmt($this->_query);
        }
        if ($error_output) {
            error_log($error_output);
        }
        return $this;
    }

    public function prepare_params()
    {
        if (count($this->_a_params)) {
            foreach ($this->_a_params as $k => &$v) {
                if (is_array($v)) {
                    $v = (string) json_encode($v);
                } elseif (!is_object($v) && !is_array($v)) {
                    if (!empty($v)) {
                        if (strpos($v, "'")) {
                            $v = str_replace("'", "%", $v);
                        }
                    }
                }
            }
        }
    }

    public function error_handler()
    {
        $error_output = '';
        if (is_array($this->_a_params)) {
            foreach ($this->_a_params as &$param) {
                if (is_object($param)) {

                } elseif (!is_string($param) && !empty($param)) {
                    if (isset($param[0])) {
                        $param = (array) $param[0];
                    }
                } elseif (!empty($param)) {
                    $param = substr($param, 0, 25);
                }
                if (is_array($param)) {
                    if (!count($param)) {
                        $param = '';
                    }
                }
            }
        }
        error_log("\n" . $this->_sql . "\n\tParams: " . print_r($this->_a_params, true) . "\n\tSql Errors: " . print_r(sqlsrv_errors(), true) . "\n\tMessage: " . $this->_message);
        $this->_error = true;
        $this->_sql_error = sqlsrv_errors();
        error_log($this->_sql_error[0]['SQLSTATE']);
        switch ($this->_sql_error[0]['SQLSTATE']) {
            /*
            case 'IMC06':
            if ($this->_retry_count < 3) {
            sleep(5);
            goto retry;
            }
            break;
            */
            case '08S02':
                exit();
            //case '01000':
            case '01S02':
                switch ($this->_sql_error[0]['code']) {
                    case '7412':
                        echo $this->_sql_error[0]['message'];
                        die($this->_sql_error[0]['message']);
                    default:
                        $this->format_message(debug_backtrace());
                        $error_output .= $this->_message;
                        break;
                }
                break;
            case '40001': //Deadlock
                if ($this->_DeadLock) {
                    $this->format_message(debug_backtrace());
                    die($this->_sql_error[0]['message']);
                }
                $this->_DeadLock = true;
                sleep(5);
                $this->_error = false;
                $this->_old_sql = $this->_sql;
            default:
                $error_output .= "\nFile: " . __FILE__ . "\nLine: " . __LINE__;
                $error_output .= "\nSQLSTATE: " . $this->_sql_error[0]['SQLSTATE'];
                $error_output .= "\n" . print_r($this->_sql_error, true);
                $error_output .= "\n\tSQL: " . $this->_sql;
                if (is_array($this->_a_params)) {
                    foreach ($this->_a_params as &$param) {
                        if (!empty($param)) {
                            if (is_object($param)) {

                            } elseif (!is_string($param) && !empty($param)) {
                                if (isset($param[0])) {
                                    $param = (array) $param[0];
                                }
                            } elseif (!empty($param)) {
                                $param = substr($param, 0, 25);
                            }
                            if (is_array($param)) {
                                if (!count($param)) {
                                    $param = '';
                                }
                            }
                        }
                    }
                }
                $error_output .= "\n\tParams: " . print_r($this->_a_params, true);
                $this->format_message(debug_backtrace());
                $error_output .= "\n" . $this->_message;
                break;
        }
        $this->format_message(debug_backtrace());
    }

    public function format_message($debug_backtrace = null)
    {
        if (php_sapi_name() == 'cli') {
            return false;
        }

        if (isset($debug_backtrace[0]['args'][0])) {
            $area = str_replace('_', ' ', $debug_backtrace[0]['args'][0]);
        }

        $file = $debug_backtrace[0]['file'];
        $line = $debug_backtrace[0]['line'];
        $function = $debug_backtrace[0]['function'];

        if ($this->_sql_error) {
            $this->_error = true;
            if (isset($this->_sql_error[0][2])) {
                $this->_sql_error = substr($this->_sql_error[0][2], strpos($this->_sql_error[0][2], '[SQL Server]') + 12);
            }

            $message = json_encode(["type" => "error", "title" => ucwords($function . " failed"), "message" => '<br>' . ucwords($function . " failed") . '<br><br>SQL Error: ' . $this->_sql_error . '<br><br>' . 'Error occurred in file: <a href="' . $file . '">' . $file . '</a> on line: ' . $line]);
            if (!$this->_NOEXEC && !headers_sent()) {
                setcookie("message", $message, -1, "/");
            }
            $this->_message = $message;
        } else {
            switch ($function) {
                case 'insert':
                    $function .= 'ed';
                    break;
                case 'delete':
                case 'update':
                    $function .= 'd';
                    break;
                case 'query': //Happens when we run an EXEC command.
                    $function = 'Executed Successfully';
                    $area = '';
                    break;
            }
            $d = new DateTime();
            if ($function == 'insert') {
                $message = json_encode(["type" => "info", "title" => '', "message" => '<i class="fa fa-database"> </i>' . ucwords($area . " " . $function) . ' ' . $d->format('h:i A'), "id" => $this->_insert_id]);
            } else {
                $message = json_encode(["type" => "info", "title" => '', "message" => '<i class="fa fa-database"> </i>' . ucwords($area . " " . $function) . ' ' . $d->format('h:i A')]);
            }
            if (php_sapi_name() != 'cli') {
                if (!headers_sent()) {
                    setcookie("message", $message, -1, "/");
                    $this->_message = $message;
                }
            }
        }
        return $this;
    }

    public function error(): string
    {
        return (isset($this->_error) ? $this->_error : '');
    }

    public function message()
    {
        return $this->_message;
    }

    public function execute(string $sql = '', array $params = []): bool
    {
        if (!empty($sql)) {
            $this->_sql = $sql;
        }
        if (count($params)) {
            $this->_a_params = $params;
        }
        $this->_sql = "EXECUTE " . $this->_sql;
        $this->_query = sqlsrv_prepare($this->_SqlSrv, $this->_sql, $this->_a_params);
        if ($this->_query) {
            return sqlsrv_execute($this->_query);
        }
        return false;
    }

    public function exists(string $table, array $params = [] /* Array of field(s) and their expected value(s) */)
    {
        $this->_a_params = $params;
        if (array_key_exists("primary_key_column", $this->_a_params)) {
            $primary_key_column = $this->_a_params['primary_key_column'];
            unset($this->_a_params['primary_key_column']);
            goto id_supplied;
        }
         $sql = "select distinct * from(SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc JOIN INFORMATION_SCHEMA.CONSTRAINT_COLUMN_USAGE ccu ON tc.CONSTRAINT_NAME = ccu.Constraint_name WHERE tc.TABLE_NAME = '$table' and tc.CONSTRAINT_TYPE = 'Primary Key' union all SELECT top(1) tc.COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS tc  WHERE tc.TABLE_NAME = '$table' AND tc.ORDINAL_POSITION=1) z";
        $data = $this->query($sql);
        $primary_key_column = 'id';
        if ($data->count()) {
            foreach ($data->_results as $d) {
                $primary_key_column = $d->COLUMN_NAME;
            }
        }
        id_supplied:
        $this->_sql = "SELECT top(1) $primary_key_column AS id from $table";

        if (isset($this->_a_params)) {
            $this->_sql .= " WHERE ";
            foreach ($this->_a_params as $p => $v) {
                switch (gettype($v)) {
                    case 'integer':
                        $this->_sql .= "[" . $p . "]=" . $v . " AND ";
                        break;
                    default:
                        $this->_sql .= "[" . $p . "]='" . $v . "' AND ";
                }
            }
            $this->_sql = substr($this->_sql, 0, -5);
        }

        $data = $this->query($this->_sql);
        if ($data->count()) {
            foreach ($data->results() as $d) {
                return $d->id;
            }
        }
        return false;
    }

    public function count()
    {
        return $this->_count;
    }

    public function field_metadata($sql = null): array
    {
        unset($this->_query);
        unset($this->_a_field_metadata);
        if (isset($sql)) {
            $this->_sql = $sql;
        }
        $this->_sql = str_replace("`", "'", $this->_sql);
        $this->_query = sqlsrv_prepare($this->_SqlSrv, $this->_sql);

        if (is_resource($this->_query)) {
            $a_fm = sqlsrv_field_metadata($this->_query);
            if (is_array($a_fm)) {
                foreach ($a_fm as $fieldMetadata) {
                    $this->_a_field_metadata[] = $fieldMetadata;
                }
            }
            if (isset($this->_a_field_metadata)) {
                return $this->_a_field_metadata;
            }
        }
        return [];
    }

    public function first()
    {
        $result = $this->results();
        if (isset($result[0])) {
            return $result[0];
        }
    }

    public function results(): array
    {
        return (isset($this->_results) ? $this->_results : []);
    }

    public function get($table, $where)
    {
        return $this->action('SELECT *', $table, $where);
    }

    public function id_exists($table, $id, $database = null): bool
    {
        if (strtolower($id) == 'null' || $id == null) {
            return false;
        }
        $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc JOIN INFORMATION_SCHEMA.CONSTRAINT_COLUMN_USAGE ccu ON tc.CONSTRAINT_NAME = ccu.Constraint_name WHERE tc.TABLE_NAME = '{$table}' and tc.CONSTRAINT_TYPE = 'Primary Key'";
        if ($database) {
            $this->query('use ' . $database . ';');
        }
        $data = $this->query($sql);
        $primary_key_column = 'id';
        if ($data->count()) {
            foreach ($data->_results as $d) {
                $primary_key_column = $d->COLUMN_NAME;
            }
        }

        id_supplied:
        $sql = "SELECT TOP(1) {$primary_key_column} FROM {$table} WHERE {$primary_key_column}={$id}";
        $data = $this->query($sql);
        if ($data->count()) {
            return true;
        } else {
            return false;
        }
    }

    public function insert($table, $fields = []): ?object
    {
        if (is_array($fields)) {
            if (count($fields)) {
                $keys = array_keys($fields);
                $values = '';
                $x = 1;
                foreach ($fields as $field) {
                    $values .= "?";
                    if ($x < count($fields)) {
                        $values .= ', ';
                    }
                    $x++;
                }
                $field_values = array_values($fields);
                foreach ($field_values as $name => $value) {
                    $this->clean_data($field_values[$name]);
                }
                $sql = "INSERT INTO $table ([" . implode('],[', $keys) . "]) VALUES ({$values}); SELECT SCOPE_IDENTITY() as ID";
                $this->_query_type = 'insert';
                if ($this->query($sql, $field_values)->error()) {
                    $this->_sql_error = sqlsrv_errors();
                    $this->format_message(debug_backtrace());
                } else {
                    $this->format_message(debug_backtrace());
                }
                return $this;
            }
        } else {
            $this->error_handler();
        }
        return $this;
    }

    public function clean_data(&$v = null)
    {
        switch (gettype($v)) {
            case 'boolean':
                return "'" . $v . "'";
        }

        /*passing a 'NULL' in the array then sets the value to a true null the database can understand*/
        if ($v === 0) {
            $v = '0';
            return;
        }
        if ($v === false || $v == "false") {
            $v = 0;
            return;
        }
        if ($v === true || $v == "true") {
            $v = 1;
            return;
        }
        if ($v == "NULL" || $v === "" || $v == "") {
            $v = NULL;
            return;
        }
        if (is_array($v)) {
            $v = (string) json_encode($v);
            return;
        }
        if (!is_object($v)) {
            if (strpos($v, "'")) {
                $v = str_replace("'", "`", $v);
            }
        }
    }

    public function insertid()
    {
        return $this->_insert_id;
    }

    public function update(string $table, string $id, $fields = [], string $id_field = null): object
    {
        $set = '';
        $x = 1;
        foreach ($fields as $name => $value) {
            $set .= "[$name] = ?";
            $this->clean_data($fields[$name]);
            if ($x < count($fields)) {
                $set .= ', ';
            }
            $x++;
        }
        if (!$id_field) {
            $id_field = 'id';
        }
        if (is_string($id)) {
            $id = "'$id'";
        }
        $sql = "UPDATE $table SET $set WHERE $id_field = $id";

        if ($this->query($sql, $fields)->error()) {
            $this->_sql_error = sqlsrv_errors();
            $this->format_message(debug_backtrace());
        } else {
            $this->format_message(debug_backtrace());
        }
        return $this;
    }
}