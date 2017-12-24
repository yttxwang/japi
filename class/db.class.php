<?
/**
 * Class dbconnect
 * 
 */
class dbconnect
{
	private static $instance;
	private $db;
	private function __construct(){
		
		$this->db = new ezSQL_mysql(DB_USER,DB_PASSWORD,DB_DATABASE,DB_HOST);
		$this->db->query("set names 'utf8'");
	}

	public function dataconnect()
	{
		if(!isset(self::$instance))
		{
			self::$instance = new dbconnect() ;
		}
		return self::$instance ;
	}

	public function getdb()
	{
		return $this->db;
	}
}


/******** userconnect *************/
class dbuserconnect
{
	private static $instance;
	private $db;
	private function __construct(){
		$this->db = new ezSQL_mysql(DB_USER,DB_PASSWORD,DB_DATABASE."_user",DB_HOST);
		$this->db->query("set names 'utf8'");
	}

	public function dataconnect()
	{
		if(!isset(self::$instance))
		{
			self::$instance = new dbuserconnect() ;
		}
		return self::$instance ;
	}

	public function getdb()
	{
		return $this->db;
	}
}
?>