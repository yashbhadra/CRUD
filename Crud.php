<?php
include_once('CustomExceptions.php');
//error_reporting(E_ERROR | E_PARSE);
class Crud
{
    /*Singleton reference to the Crud object*/
    public static $crud;
    /*static function to get refeerence to the singleton object
    returns $crud */
    public static function getInstance($servername,$username,$password,$db){
        if(self::$crud == NULL){
            self::$crud = new Crud($servername,$username,$password,$db);
        }
        return self::$crud;
    }
    //public $v; variable definition should be with the access specifier.
    //Any variable defined within the constuctor should be done with the help of this pointer.
    //private
    function __construct($servername,$username,$password,$db)
    {
        $this->conn = new mysqli($servername, $username,$password,$db);
        try
        {
            if ($this->conn->connect_error) {
                throw new ConnectionError($this->conn->connect_error);
            }
        }
        catch(ConnectionError $ce)
        {
            throw $ce;
        }
    }
    /*function which accepts table name and an aaray of ids to delete*/
    function deleteData($table,array $del_array,$whereParam)
    {
        try
        {
            if(!$this->checkIfTableExists($table))
            {
                throw new TableNotExists($table);
            }
            $bindTypes=$this->getBindType($del_array);
            $questionString=$this->getPlaceholder(count($del_array));
            // $query="delete from $table where id IN($this->arr)";
            $stmt=$this->conn->prepare("delete from $table where $whereParam IN($questionString);");
            call_user_func_array(array($stmt, 'bind_param'),array_merge(array($bindTypes),$this->getParams($del_array)));
            if($stmt->execute())
            {
                $ar=$stmt->affected_rows;
                return $ar;
            }
        }
        catch(TableNotExists $tne)
        {
            throw $tne;
        }
    }
    /*function accepts a table name, column list and list of values to be inserted in the table*/
    function insert($table,array $columns,array $values)
    {
        try
        {
            // if(!$this->checkIfTableExists($table))
            //{
            //    throw new TableNotExists($table);
            //    }
            //   if(count($columns)!=count($values))
            //  {
            //     throw new UnmatchedColumnValueList(count($columns),count($values));
            //}
            $bindTypes = $this->getBindType($values);//get the bind type for each value. eg it will return ss or sss or si etc
            $this->insertData($table,$columns,$values,$bindTypes);//call the master insert
        }
        catch(TableNotExists $tne)
        {
            throw $tne;
        }
        catch(UnmatchedColumnValueList $ucv)
        {
            throw $ucv;
        }
    }
    /*inserts the values by getting the bindtypes and placeholder values
    */
    function insertData($table,array $columns,array $values,$bindTypes)
    {
        try
        {
            if(!$this->checkIfTableExists($table))
            {
                throw new TableNotExists($table);
            }
            if(count($columns)!=count($values))
            {
                throw new UnmatchedColumnValueList(count($columns),count($values));
            }
            $questionMarkString = $this->getPlaceholder(count($values));//returns string with number of question marks
            $columnList = implode(',',$columns);//Get columns in a comma separated manner
            $stmt = $this->conn->prepare("INSERT INTO $table($columnList) VALUES($questionMarkString);");//Prepare the statement using the column list and question mark string
            call_user_func_array(array($stmt, 'bind_param'),array_merge(array($bindTypes), $this->getParams($values)));//uses to pass bind params to mysqli bind_param,$this->getParams($values) returns array with reference paramters required by bind_param
            $stmt->execute();
            //Execute the statement, if it executes correctly
            $ar=$stmt->affected_rows;
        }
        catch(TableNotExists $tne)
        {
            throw $tne;
        }
        catch(UnmatchedColumnValueList $ucv)
        {
            throw $ucv;
        }
    }
    /*returns a string specifying data types of array list passed as actual parameter
    example array("","",int) would yeild "ssi"
    */
    function getBindType(array $values)
    {
        $dt="";
        $ar=array();
        for($i=0;$i<count($values);$i++)
        {
            $dt=gettype($values[$i]);
            switch($dt)
            {
                case "integer":
                {
                    $ar[]="i";
                    break;
                }
                case "string":
                {
                    $ar[]="s";
                    break;
                }
                case "double":
                {
                    $ar[]="d";
                    break;
                }
                default:
                {
                    $ar[]="b";
                }
            }
        }
        return implode('',$ar);//glue the parameters without spaces between them
    }
    /*returns a string containing ? (placeholder) equivalent to $count to be used as placeholder in prepare
    statements */
    function getPlaceHolder($count)
    {
        $qt=array();
        for($i=0;$i<$count;$i++)
        {
            $qt[]="?";
        }
        return implode(",",$qt);// glue question marks using , as delimiter
    }
    /*returns a reference of array to be used by call_user_func*/
    private function getParams(array $values)
    {
        $params= array();
        for($i = 0; $i< count($values); $i++)// loop to make the passed array as reference array
        {
            $params[$i] = &$values[$i];
        }
        return $params;//return referenced array
    }
    /*Returns an array  of all the records fetched from the $table containing columns specified
    in $columns (put * for all the columns) and has a predicat where condition on table $whereParam
    "where $whereParam = $id"*/
    function getData($id,$table,array $columns,$whereParam)
    {
        try
        {
            if(!$this->checkIfTableExists($table))
            {
                throw new TableNotExists($table);
            }
            if($columns[0] == "*"){
                $columnList = "*";
            }
            else{
                $columnList = implode(',',$columns);
            }
            $stmt = $this->conn->prepare("select $columnList from $table where $whereParam=?");
            $bindTypes = $this->getBindType(array($id));
            $stmt->bind_param($bindTypes, $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $myArray=array();
            while($row=$result->fetch_assoc())//Loop to copy the contents of row to an array
            { 
                $myArray[]=$row;
            }
            return $myArray; // returning the resultset
        }
        catch(TableNotExists $tne)
        {
            throw $tne;
        }
        catch(UnmatchedColumnValueList $ucv)
        {
            throw $ucv;
        }
    }
    /*returns json array enclosed within a json object  */
    function getJsonArray($table,array $array) //returns JSON array
    {
        $jsonString = '{"'.$table.'":'.json_encode($array)."}";//append table name to the json encode output
        return $jsonString;//return the final json string
    }
    /*Returns a json array of passed array */
    function getJson(array $array)//returns jSon Object
    {
        return json_encode($array);
    }
    /*Following code will generate a list of columns to be updated with placeholder '?'
    example userId=?,name=?,salary=?*/
    protected function getUpdatePlaceholder(array $columns){
        $columnList="";
        foreach($columns as $i)
        {
            $columnList.="$i=?,";
        }
        $columnList=rtrim($columnList,",");
        return $columnList;
    }
    /*Takes the table name , list of columns to update, list of values corresponding to column
    , a column name for where clause and $id for where subject
    "where $whereParam = $id*/
    function updateData($id,$table,array $columns,array $values,$whereParam)
    {
        try
        {
            if(!$this->checkIfTableExists($table))
            {
                throw new TableNotExists($table);
            }
            if(count($columns)!=count($values))
            {
                throw new UnmatchedColumnValueList(count($columns),count($values));
            }
            array_push($values,$id);
            $bindTypes = $this->getBindType($values);//get the bind type for each value. eg it will return ss or sss or si etc
            $columnList = $this->getUpdatePlaceHolder($columns);
            $stmt = $this->conn->prepare("UPDATE $table set $columnList where $whereParam=?;");
            //$params=array_merge(array($bindTypes),$this->getParams($values));
            call_user_func_array(array($stmt, 'bind_param'),array_merge(array($bindTypes),$this->getParams($values)));//uses to pass bind params to mysqli bind_param
            if($stmt->execute())//Execute the statement, if it executes correctly
            { 
                $ar=$stmt->affected_rows;
                return $ar;
            }
        }
        catch(TableNotExists $tne)
        {
            throw $tne;
        }
        catch(UnmatchedColumnValueList $ucv)
        {
            throw $ucv;
        }
    }
    /*returns a description of table if it exists otherwise returns false */
    function checkIfTableExists($table)
    {
        $query = "DESCRIBE  $table";
        $result = $this->conn->query($query);
        return $result;
    }
    /*function __destruct(){
        $this->conn->close();
    }*/
    function getLastInsertedID()
    {
        return $this->conn->insert_id;
    }
    function getDateTime()
    {
        $stmt = $this->conn->prepare("select NOW()");
        $stmt->execute(); 
        $result = $stmt->get_result();
        $array = $result->fetch_assoc();
        return $array['NOW()'];
    }
    /*Takes a raw query executes it and returns the output result set */
    public function executeQuery($query){
        $result = $this->conn->query($query);
        return $result;
    }
}
?>