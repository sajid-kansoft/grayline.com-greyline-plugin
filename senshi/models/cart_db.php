<?php
/**
 * cart_db.php
 * User: Iona iona@palisis.com
 * Date: 2020-09-07
 * Time: 10:07
 */


class CartDb
{
    protected $dbtype;
    protected $dbhost;
    protected $dbport;
    protected $dbname;
    protected $dbuser;
    protected $dbpass;

    public function __construct()
    {
        // this works to include library config file ok
        include("tourcms/config.php");
        $this->dbtype = $dbtype;
        $this->dbhost = $dbhost;
        $this->dbport = $dbport;
        $this->dbname = $dbname;
        $this->dbuser = $dbuser;
        $this->dbpass = $dbpass;
    }

    public function getConn()
    {
        // Database connection object
        $conn = new PDO($this->dbtype . ":host=" . $this->dbhost . ";port=" . $this->dbport . ";dbname=" . $this->dbname . "", $this->dbuser,$this->dbpass);
        return $conn;
    }
}
