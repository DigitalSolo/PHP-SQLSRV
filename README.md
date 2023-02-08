# PHP-SQLSRV
Class for interacting with SQL Server<br>
<h3>Configuration</h3>
<h4>Set DB Variables</h4>
Within the DB.php file set the four constants<br>
SQLHOST = 'Host' # examples are localhost, domain name or IP addres<br>
 SQLUID = 'User ID'<br>
  SQLPW = 'Password'<br>
  SQLDB = 'Database'<br>
<h3>Usage</h3>
<h4>Query</h4>
$data = DB::getInstance()->query("SELECT TOP(10) column1, column2 FROM MyTable");<br>
if ($data->count()) {<br>
&nbsp;&nbsp;foreach ($data->results() as $d) {<br>
&nbsp;&nbsp;&nbsp;&nbsp;$column1 = $d->column1;<br>
&nbsp;&nbsp;&nbsp;&nbsp;$column1 = $d->column2;<br>
&nbsp;&nbsp;}<br>
} <br>

<h4>Update</h4>
DB::getInstance()->update("TableName", PrimaryKeyValue, ["column1" => NewValue, "column2" => NewValue]);<br>

<h4>Delete</h4>
DB::getInstance()->delete("TableName", ["column1", "=", Value]);<br>
