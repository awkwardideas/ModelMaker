<?php
namespace AwkwardIdeas\ModelMaker;

use AwkwardIdeas\MyPDO\MyPDO as DB;
use AwkwardIdeas\MyPDO\SQLParameter;

class ModelMaker{
    private $connection = [
        "host"=>"",
        "database"=>"",
        "username"=>"",
        "password"=>""
    ];
    private $process=false;
    private $db;

    public function __construct()
    {
        self::GetConnectionData();
        $this->db = new DB();
    }

    private function GetConnectionData(){
        $filePath = getcwd().'/.env';
        if (file_exists($filePath)) {
            $handle = @fopen($filePath, "r");
            if ($handle) {
                while (($buffer = fgets($handle, 4096)) !== false) {
                    $value = self::GetEnvVariable("DB_HOST", $buffer);
                    if ($value !== false){
                        $this->connection["host"] = $value;
                        continue;
                    }
                    $value = self::GetEnvVariable("DB_DATABASE", $buffer);
                    if ($value !== false){
                        $this->connection["database"] = $value;
                        continue;
                    }
                    $value = self::GetEnvVariable("DB_USERNAME", $buffer);
                    if ($value !== false){
                        $this->connection["username"] = $value;
                        continue;
                    }
                    $value = self::GetEnvVariable("DB_PASSWORD", $buffer);
                    if ($value !== false){
                        $this->connection["password"] = $value;
                        continue;
                    }
                }
                if (!feof($handle)) {
                    echo "Error: unexpected fgets() fail\n";
                }
                fclose($handle);
            }
        }

        if($this->connection["host"]!="" && $this->connection["database"]!="" && $this->connection["username"]!="" && $this->connection["password"]!="") $this->process=true;
    }

    private function GetEnvVariable($variableName, $buffer){
        if (strpos(strtoupper($buffer), $variableName."=") > -1) {
            $removeFromFileValue = "/[\n\r]/";
            return preg_replace($removeFromFileValue, '', after("=", $buffer));
        }else{
            return false;
        }
    }

    private function EstablishConnection(){
        if(!$this->process)
            return "<p>Required connection data not found in .env</p>";

        if($this->db->EstablishConnections($this->GetHost(), $this->GetDatabase(), $this->GetUsername(), $this->GetPassword(), $this->GetUsername(), $this->GetPassword()))
            return "<p>Connected to <b>".$this->GetDatabase()."</b> on <b>".$this->GetHost()."</b>.</p>";
        else
            return "<p>Unable to connect. Please verify permissions.</p>";
    }

    private function CloseConnection(){
        $this->db->CloseConnections();
        $this->process = false;
    }

    public function GetHost(){
        return $this->connection["host"];
    }

    public function GetDatabase(){
        return $this->connection["database"];
    }

    public function SetDatabase($database){
        $this->CloseConnection();
        $this->connection["database"] =$database;
        $this->process = true;
        $this->EstablishConnection();
    }

    public function GetUsername(){
        return $this->connection["username"];
    }

    public function GetPassword(){
        return $this->connection["password"];
    }

    public static function CleanModelMakerDirectory(){
        $myLaravel = new ModelMaker();
        $modelMakerDirectory = $myLaravel->GetModelMakerDirectory();
        array_map('unlink', glob( "$modelMakerDirectory*.php"));

        return "Model Maker Files Deleted in $modelMakerDirectory";
    }

    public static function GenerateModels($database, $namespace, $connection){
        $myLaravel = new ModelMaker();
        if($database!=""){
            $myLaravel->SetDatabase($database);
        }
        $tables = $myLaravel->GetTables();
        foreach ($tables as $table) {
            $tablename = $table[0];
            $myLaravel->CreateModelFile($tablename, $namespace, $connection);
        }
        return "New Model Files Created in " . self::GetModelMakerDirectory();
    }

    private static function GetModelMakerDirectory(){
        return getcwd().'/app/models/';
    }

    public function GetTables(){
        $query = "show tables;";
        $tables = $this->db->Query($query);
        return $tables;
    }

    public function DescribeTable($tablename){
        $query = "describe `" . $tablename . "`;";
        $columns = $this->db->Query($query);
        return $columns;
    }

    public function CreateModelFile($tablename, $namespace, $connection){
        $fileData = $this->GetFileOutput($tablename, $namespace, $connection);
        $fileName = $this->GetFileName($tablename);
        $dir = self::GetModelMakerDirectory();

        $parts = explode('/', $dir);
        $file = array_pop($parts);
        $dir = '';
        foreach($parts as $part)
            if(!is_dir($dir .= "/$part")) mkdir($dir);
        return file_put_contents("$dir/$fileName", $fileData);
    }

    private function ConnectToDatabase(){
        $this->db = new DB();
        $output="";
        if($this->db->EstablishConnections($this->GetHost(), $this->GetDatabase(), $this->GetUsername(), $this->GetPassword(), $this->GetUsername(), $this->GetPassword()))
            $output.= "<p>Connected to <b>".$this->GetDatabase()."</b> on <b>".$this->GetHost()."</b>.</p>";
        else
            $output.= "<p>Unable to connect. Please verify permissions.</p>";
        return $output;
    }

    private function GetFileName($tablename){
        return ucwords($tablename) . ".php";
    }

    private function GetFileOutput($tablename, $namespace, $connection)
    {
        $columns = $this->DescribeTable($tablename);
        $output = "<?php" . PHP_EOL
            . PHP_EOL
            . "namespace $namespace;" . PHP_EOL
            . PHP_EOL
            . "use Illuminate\Database\Eloquent\Model;" . PHP_EOL
            . PHP_EOL
            . "class " . ucwords($tablename) . " extends Model" . PHP_EOL
            . "{" . PHP_EOL
            . self::GetModelCode($tablename, $columns, $connection, indent())
            . "}";
        $output .= PHP_EOL . PHP_EOL . self::CommentTableStructure($columns, indent());
        return $output;
    }

    private function CommentTableStructure($columns, $indentation){
        $output = $indentation . "/**" . PHP_EOL
            . $indentation . " *" . PHP_EOL;
        foreach ($columns as $columndata) {
            $output .= $indentation . " * " . $columndata["Field"] . "	" . $columndata["Type"] . "	" . $columndata["Null"] . "	" . $columndata["Key"] . "	" . $columndata["Default"] . "	" . $columndata["Extra"] . "	" . PHP_EOL;
        }
        $output .= $indentation . " *" . PHP_EOL
            . $indentation . " */" . PHP_EOL;

        return $output;
    }

    private function GetModelCode($tablename, $columns, $connection, $indentation){
        $output ="";
        if($connection!=""){
            $output = $indentation. self::GetModelDatabase($connection, $indentation);
        }
        $output .= self::GetModelTableName($tablename, $indentation);
        $output .= self::GetTimestampsCode($indentation);
        $output .= self::GetModelPrimaryKey($tablename, $columns, $indentation);
        $output .= self::GetFillables($columns, $indentation);
        return $output;
    }


    private function GetModelDatabase($connection, $indentation){
        return $indentation . "/**" . PHP_EOL
        . $indentation . " * The connection name for the model." . PHP_EOL
        . $indentation . " *" . PHP_EOL
        . $indentation . " * @var string" . PHP_EOL
        . $indentation . " */" . PHP_EOL
        . PHP_EOL
        . $indentation . 'protected $connection = \'' . $connection . '\';' . PHP_EOL;
    }

    private function GetModelTableName($tablename, $indentation){
        return $indentation . "/**" . PHP_EOL
        . $indentation . " * The table associated with the model." . PHP_EOL
        . $indentation . " *" . PHP_EOL
        . $indentation . " * @var string" . PHP_EOL
        . $indentation . " */" . PHP_EOL
        . PHP_EOL
        . $indentation . 'protected $table = \'' . $tablename .'\';' . PHP_EOL;
    }

    private function GetTimestampsCode($indentation){
        return $indentation . "/**" . PHP_EOL
            . $indentation . " * Indicates if the model should be timestamped." . PHP_EOL
            . $indentation . " *" . PHP_EOL
            . $indentation . " * @var bool" . PHP_EOL
            . $indentation . " */" . PHP_EOL
            . PHP_EOL
            . $indentation . 'public $timestamps = false;' . PHP_EOL;

    }

    private function GetModelPrimaryKey($tablename, $columns, $indentation)
    {
        $output = "";
        $columnData = self::GetColumnData($tablename, $columns, $indentation);
        if(count($columnData['primaryKeys']) == 1){
            $output .= $indentation . "/**" . PHP_EOL
                . $indentation . " * The column which is the primary key." . PHP_EOL
                . $indentation . " *" . PHP_EOL
                . $indentation . " * @var string" . PHP_EOL
                . $indentation . " */" . PHP_EOL
                . PHP_EOL;
            $output .=  $indentation . 'protected $primaryKey = \'' . $columnData['primaryKeys'][0] .'\';' . PHP_EOL;
            if(count($columnData['autoIncrement']) > 0 && in_array($columnData['primaryKeys'][0], $columnData['autoIncrement']) ){
                $output .= $indentation . '$incrementing = false;' . PHP_EOL;
            }
        }

        return $output;
    }

    private function GetFillables($columns, $indentation){
        $output = $indentation . "/**" . PHP_EOL
            . $indentation . " * The attributes that are mass assignable." . PHP_EOL
            . $indentation . " *" . PHP_EOL
            . $indentation . " * @var array" . PHP_EOL
            . $indentation . " */" . PHP_EOL
            . $indentation . 'protected $fillable = [';

        foreach($columns as $columndata){
            $output.= "'".$columndata["Field"]."',";
        }
        
        $output .= '];'. PHP_EOL;
        return $output;
    }

    private function GetColumnData($tablename, $columns, $indentation){
        $tabledata = [
            "foreignKeys" => [],
            "primaryKeys" => [],
            "indexes" => [],
            "uniques" => [],
            "autoIncrement" => []
        ];

        foreach ($columns as $columndata) {
            if (strpos(strtoupper($columndata["Extra"]), "AUTO_INCREMENT") > -1) {
                $tabledata["autoIncrement"][] = $columndata["Field"];
            }
            if (strpos(strtoupper($columndata["Key"]), "PRI") > -1 && ($columndata["Extra"] == "" || strpos(strtoupper($columndata["Extra"]), "AUTO_INCREMENT") == -1)) {
                $tabledata["primaryKeys"][] = $columndata["Field"];
            }
            if (strpos(strtoupper($columndata["Key"]), "MUL") > -1) {
                $tabledata["foreignKeys"][] = self::GetForeignKeys($tablename, $columndata["Field"], $indentation . indent());
            }
        }
        $inheritUnique = array_merge($tabledata["autoIncrement"], $tabledata["primaryKeys"]);

        $tabledata["indexes"][] = self::GetIndexes($tablename, $inheritUnique, $indentation . indent());
        $tabledata["uniques"][] = self::GetUniques($tablename, $inheritUnique, $indentation . indent());

        return $tabledata;
    }

    private function AddColumnByDataType($coldata)
    {
        $name = $coldata["Field"];
        $typedata = $coldata["Type"];
        $null = $coldata["Null"];
        $key = $coldata["Key"];
        $default = $coldata["Default"];
        $extra = $coldata["Extra"];

        $type = before('(', $typedata);
        $data = between('(', ')', $typedata);
        $info = after(')', $typedata);

        $migrationCall = '$table->';

        switch (strtoupper($type)) {
            //      $table->bigIncrements('id');	Incrementing ID (primary key) using a "UNSIGNED BIG INTEGER" equivalent.
            //      $table->bigInteger('votes');	BIGINT equivalent for the database.
            case 'BIGINT':
                if (strpos(strtoupper($extra),"AUTO_INCREMENT") > -1) {
                    $migrationCall .= 'bigIncrements(\'' . $name . '\')';
                } else {
                    $migrationCall .= 'bigInteger(\'' . $name . '\')';
                }
                break;
            //      $table->binary('data');	BLOB equivalent for the database.
            case 'BINARY':
                $migrationCall .= 'binary(\'' . $name . '\')';
                break;
            case 'BIT':
                $migrationCall .= 'boolean(\'' . $name . '\')';
                if($default!=""){
                    $default = (strpos($default,'0')>-1) ? "0" : "1";
                }
                break;
            //      $table->boolean('confirmed');	BOOLEAN equivalent for the database.
            case 'BOOLEAN':
                $migrationCall .= 'boolean(\'' . $name . '\')';
                break;
            //      $table->char('name', 4);	CHAR equivalent with a length.
            case 'CHAR':
                $migrationCall .= 'char(\'' . $name . '\', ' . $data . ')';
                break;
            //      $table->date('created_at');	DATE equivalent for the database.
            case 'DATE':
                $migrationCall .= 'date(\'' . $name . '\')';
                break;
            //      $table->dateTime('created_at');	DATETIME equivalent for the database.
            case 'DATETIME':
                $migrationCall .= 'dateTime(\'' . $name . '\')';
                break;
            //      $table->decimal('amount', 5, 2);	DECIMAL equivalent with a precision and scale.
            case 'DECIMAL':
                $migrationCall .= 'decimal(\'' . $name . '\', ' . $data . ')';
                break;
            //      $table->double('column', 15, 8);	DOUBLE equivalent with precision, 15 digits in total and 8 after the decimal point.
            case 'DOUBLE':
                $migrationCall .= 'double(\'' . $name . '\', ' . $data . ')';
                break;
            //      $table->enum('choices', ['foo', 'bar']);	ENUM equivalent for the database.
            case 'ENUM':
                $migrationCall .= 'enum(\'' . $name . '\', [' . $data . '])';
                break;
            //      $table->float('amount');	FLOAT equivalent for the database.
            case 'FLOAT':
                $migrationCall .= 'float(\'' . $name . '\')';
                break;
            //      $table->increments('id');	Incrementing ID (primary key) using a "UNSIGNED INTEGER" equivalent.
            //      $table->integer('votes');	INTEGER equivalent for the database.
            case 'INT':
                if (strpos(strtoupper($extra),"AUTO_INCREMENT") > -1) {
                    $migrationCall .= 'increments(\'' . $name . '\')';
                } else {
                    $migrationCall .= 'integer(\'' . $name . '\')';
                }
                break;
            //      $table->json('options');	JSON equivalent for the database.
            case 'JSON':
                $migrationCall .= 'json(\'' . $name . '\')';
                break;
            //      $table->jsonb('options');	JSONB equivalent for the database.
            case 'JSONB':
                $migrationCall .= 'jsonb(\'' . $name . '\')';
                break;
            //      $table->longText('description');	LONGTEXT equivalent for the database.
            case 'LONGTEXT':
                $migrationCall .= 'longText(\'' . $name . '\')';
                break;
            //      $table->mediumInteger('numbers');	MEDIUMINT equivalent for the database.
            case 'MEDIUMINT':
                $migrationCall .= 'mediumInteger(\'' . $name . '\')';
                break;
            //      $table->mediumText('description');	MEDIUMTEXT equivalent for the database.
            case 'MEDIUMTEXT':
                $migrationCall .= 'mediumText(\'' . $name . '\')';
                break;
            //      $table->morphs('taggable');	Adds INTEGER taggable_id and STRING taggable_type.
            case 'MORPHS':
                $migrationCall .= 'morphs(\'' . $name . '\')';
                break;
            //      $table->nullableTimestamps();	Same as timestamps(), except allows NULLs.
            case 'NULL_TIMESTAMPS':
                $migrationCall .= 'nullableTimestamps()';
                break;
            //      $table->rememberToken();	Adds remember_token as VARCHAR(100) NULL.
            case 'REMEMBER':
                $migrationCall .= 'rememberToken()';
                break;
            //      $table->smallInteger('votes');	SMALLINT equivalent for the database.
            case 'SMALLINT':
                $migrationCall .= 'smallInteger(\'' . $name . '\')';
                break;
            //      $table->softDeletes();	Adds deleted_at column for soft deletes.
            case 'SOFTDELETES':
                $migrationCall .= 'softDeletes()';
                break;
            //      $table->string('email');	VARCHAR equivalent column.
            //      $table->string('name', 100);	VARCHAR equivalent with a length.
            case 'VARCHAR':
                if ($data != "") {
                    $migrationCall .= 'string(\'' . $name . '\', ' . $data . ')';
                } else {
                    $migrationCall .= 'string(\'' . $name . '\')';
                }
                break;
            //      $table->text('description');	TEXT equivalent for the database.
            case 'TEXT':
                $migrationCall .= 'text(\'' . $name . '\')';
                break;
            //      $table->time('sunrise');	TIME equivalent for the database.
            case 'TIME':
                $migrationCall .= 'time(\'' . $name . '\')';
                break;
            //      $table->tinyInteger('numbers');	TINYINT equivalent for the database.
            case 'TINYINT':
                if($data==1){
                    $migrationCall .= 'boolean(\'' . $name . '\')';
                }else{
                    $migrationCall .= 'tinyInteger(\'' . $name . '\')';
                }
                break;
            //      $table->timestamp('added_on');	TIMESTAMP equivalent for the database.
            case 'TIMESTAMP':
                $migrationCall .= 'timestamp(\'' . $name . '\')';
                break;
            //      $table->timestamps();	Adds created_at and updated_at columns.
            case 'TIMESTAMPS':
                $migrationCall .= 'timestamps()';
                break;
            //      $table->uuid('id');
            case 'YEAR':
                $migrationCall .= 'tinyInteger(\'' . $name . '\')';
                break;
            case 'UUID':
                $migrationCall .= 'uuid(\'' . $name . '\')';
                break;
            default:
                return false;
        }

        if(strpos(strtoupper($info), " UNSIGNED") > -1){
            $migrationCall .= "->unsigned()";
        }

        if(strtoupper($null) == "YES"){
            $migrationCall .= "->nullable()";
        }

        if($default != ""){
            if($default=="CURRENT_TIMESTAMP"){
                $migrationCall .= "->useCurrent()";
                //Needs on update use current_timestamp feature if in extra
            }else{
                $migrationCall .= "->default('".addslashes($default)."')";
            }

        }

        return $migrationCall;
    }

    private function GetIndexes($tablename, $primaryKeys, $indentation){
        $schemaname = $this->GetDatabase();
        $sqlQuery = "SELECT DISTINCT GROUP_CONCAT(COLUMN_NAME) as COLUMN_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=:schemaname AND TABLE_NAME=:tablename AND Non_unique=1 AND INDEX_NAME <> 'PRIMARY' GROUP BY INDEX_NAME;";
        $relations = $this->db->Query($sqlQuery, [new SQLParameter(":schemaname",$schemaname), new SQLParameter(":tablename",$tablename)]);
        $indexCall="";
        foreach($relations as $relation) {
            $columns = $relation['COLUMN_NAME'];
            if(in_array($columns, $primaryKeys)){
                continue;
            }
            $columns = array_filter(explode(",",$columns));
            $identifierName = self::GetIdentifier($tablename, implode("_", $columns), "index");
            if (count($columns) > 1) {

                $indexCall .= $indentation . '$table->index([\'' . implode("','", $columns) . '\'], \'' . $identifierName . '\');' . PHP_EOL;
            } else {
                $indexCall .= $indentation . '$table->index(\'' . implode($columns) . '\', \'' . $identifierName . '\');' . PHP_EOL;
            }
        }
        return $indexCall;
    }

    private function GetUniques($tablename, $primaryKeys, $indentation){
        $schemaname = $this->GetDatabase();
        $sqlQuery = "SELECT DISTINCT GROUP_CONCAT(COLUMN_NAME) as COLUMN_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=:schemaname AND TABLE_NAME=:tablename AND Non_unique=0 AND INDEX_NAME <> 'PRIMARY' GROUP BY INDEX_NAME;";
        $relations = $this->db->Query($sqlQuery, [new SQLParameter(":schemaname",$schemaname), new SQLParameter(":tablename",$tablename)]);
        $uniqueCall="";

        foreach($relations as $relation) {
            $columns = $relation['COLUMN_NAME'];
            if(in_array($columns, $primaryKeys)){
                continue;
            }
            $columns = array_filter(explode(",",$columns));
            $identifierName = self::GetIdentifier($tablename, implode("_", $columns), "unique");
            if (count($columns) > 1) {
                $uniqueCall .= $indentation . '$table->unique([\'' . implode("','", $columns) . '\'], \'' . $identifierName . '\');' . PHP_EOL;
            } else {
                $uniqueCall .= $indentation . '$table->unique(\'' . implode($columns) . '\', \'' . $identifierName . '\');' . PHP_EOL;
            }
        }
        return $uniqueCall;
    }

    private function GetForeignKeys($tablename, $columnname, $indentation){
        $schemaname = $this->GetDatabase();
        $sqlQuery = "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=:schemaname AND TABLE_NAME=:tablename AND COLUMN_NAME=:columnname AND REFERENCED_TABLE_NAME IS NOT NULL AND REFERENCED_COLUMN_NAME IS NOT NULL;";
        $relations = $this->db->Query($sqlQuery, [new SQLParameter(":schemaname",$schemaname), new SQLParameter(":tablename",$tablename), new SQLParameter(":columnname",$columnname)]);
        $foreignCall="";
        foreach($relations as $relation) {
            $foreignCall.= $indentation . '$table->foreign(\'' . $relation['COLUMN_NAME'] . '\')->references(\'' . $relation['REFERENCED_COLUMN_NAME'] . '\')->on(\'' . $relation['REFERENCED_TABLE_NAME'] . '\');' . PHP_EOL;
        }
        return $foreignCall;
    }

    private function DropForeignKeys($tablename, $indentation){
        $schemaname = $this->GetDatabase();
        $sqlQuery = "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=:schemaname AND TABLE_NAME=:tablename AND REFERENCED_TABLE_NAME IS NOT NULL AND REFERENCED_COLUMN_NAME IS NOT NULL;";
        $relations = $this->db->Query($sqlQuery, [new SQLParameter(":schemaname",$schemaname), new SQLParameter(":tablename",$tablename)]);
        $foreignCall="";
        foreach($relations as $relation) {
            $foreignCall.= $indentation . indent() . '$table->dropForeign([\'' . $relation['COLUMN_NAME'] . '\']);' . PHP_EOL;
        }

        if($foreignCall!=""){
            $foreignCall = self::SchemaTableWrap($tablename,$foreignCall,$indentation);
        }

        return $foreignCall;
    }

    private function SchemaCreateWrap($tablename, $content, $indentation){
        $wrap = $indentation . 'Schema::create(\'' . $tablename . '\', function (Blueprint $table){' . PHP_EOL
            . $content
            . $indentation . '});' . PHP_EOL;

        return $wrap;
    }

    private function SchemaTableWrap($tablename, $content, $indentation){
        $wrap = $indentation . 'Schema::table(\'' . $tablename . '\', function ($table) {' . PHP_EOL
            . $content
            . $indentation . '});' . PHP_EOL;

        return $wrap;
    }

    private function GetIdentifier($tablename, $columns, $type){
        $maxCharacters = 60; //64, but reducing to avoid issues
        $identifier = $tablename."_".$columns."_".$type;
        if(strlen($identifier) > $maxCharacters){
            $constraint = strlen($tablename."_".$type);
            $columns = explode("_",$columns);
            $remainder = $maxCharacters - $constraint - count($columns);
            $permit = ($remainder - ($remainder % count($columns))) / count($columns);
            $identifier =$tablename."_";
            foreach($columns as $column){
                $identifier .= substr($column,0,$permit)."_";
            }
            $identifier .= $type;
        }
        return $identifier;
    }
}