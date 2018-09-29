<?php
$username = "root" ;
$password = "" ;
$host = "localhost" ;
$dbname = "task_gen" ;

if( session_status() == PHP_SESSION_NONE ){
    session_start() ;
}

$con = mysqli_connect($host,$username,$password,$dbname) ;

if( !$con ){
    die("Connection failed : ".mysql_connect_error()) ;
}
mysqli_set_charset($con,'utf8') ;

?>